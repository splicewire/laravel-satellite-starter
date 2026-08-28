# The standard beam/satellite-tier deploy: builds the container image every site deploys to Cloud
# Run via .github/workflows/deploy.yml + splicewire:beam:provision (splicewire/laravel-beam-provision).
# Proven live against beam-pilot-gcp-cloud-run (gcp-cloud-run-provisioning map, tickets 10 + the CI
# follow-on); baked into laravel-beam-starter and laravel-satellite-starter identically.
#
# Single builder stage, not split PHP/Node stages. The ORIGINAL reason is now HISTORY:
# @laravel/vite-plugin-wayfinder's `vite build` shelled out to `php artisan wayfinder:generate`, so
# the frontend build needed a booted (vendor-installed) Laravel app and not just Node — found live,
# not assumed. Wayfinder is retired fleet-wide as of beam-runbook ADR-0004 (2026-08-27) and `vite
# build` no longer touches php, so splitting into separate PHP and Node stages is now POSSIBLE.
# It has deliberately NOT been done here: this file's job today is to keep building the image it
# already builds, and whether the split is worth its cost is the deploy owner's call.

FROM php:8.4-cli-bookworm AS builder
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev ca-certificates curl gnupg \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath exif \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN corepack enable

WORKDIR /app
COPY composer.json composer.lock ./
# `gh_app_token` is a build secret (never baked into an image layer), passed only when a host has
# private packages to resolve — satellite-tier's own family, splicewire-market's App reused for our
# internal CI (see .github/workflows/deploy.yml). `required=false` + the `-s` check make this a
# no-op for beam-tier hosts, which never pass it: all-public deps, ordinary `composer install`.
RUN --mount=type=secret,id=gh_app_token,required=false \
    if [ -s /run/secrets/gh_app_token ]; then \
        git config --global url."https://x-access-token:$(cat /run/secrets/gh_app_token)@github.com/".insteadOf "https://github.com/"; \
    fi \
    && composer install --no-dev --no-scripts --no-interaction --optimize-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# A throwaway .env for the frontend build. `php artisan key:generate` used to run here too, solely
# so `artisan wayfinder:generate` could boot the framework during `vite build`. Wayfinder is retired
# fleet-wide (beam-runbook ADR-0004, 2026-08-27), nothing in the build below boots artisan any more,
# and the APP_KEY it wrote was never read by the build — vite only exposes VITE_-prefixed keys, and
# laravel-vite-plugin reads ASSET_URL/APP_URL. So that step is gone.
#
# The `cp` is NOT dead weight and deliberately stays: vite loads this .env, and `.env.example`'s
# `VITE_APP_NAME="${APP_NAME}"` is compiled into the bundle (resources/js/app.tsx reads
# `import.meta.env.VITE_APP_NAME`). Verified by probe — a sentinel VITE_APP_NAME lands in app-*.js.
# The .env is still never copied into the runtime stage below; real values are injected via Cloud
# Run env vars at deploy time.
RUN cp .env.example .env \
    && pnpm install --no-frozen-lockfile --ignore-scripts \
    && pnpm run build \
    && rm .env

FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath exif \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=builder /app /var/www/html
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chmod -R a+rwX storage bootstrap/cache

ENV APP_ENV=production
ENV LOG_CHANNEL=stderr
# Laravel's own docs document a bare socket-directory `host` as PDO pgsql's way to address a Unix
# socket — Cloud Run's Auth Proxy volume mount exposes it at /cloudsql/<connection_name> (ticket
# 01's finding). Real value is exported by entrypoint.sh from DB_CREDENTIALS_JSON at boot.
ENV DB_CONNECTION=pgsql

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
