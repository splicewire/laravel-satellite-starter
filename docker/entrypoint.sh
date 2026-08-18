#!/bin/sh
set -e

# tenant-database's secret is one JSON blob (ticket 07's deliberate choice — never a plaintext
# per-field output). This is where that blob actually becomes the DB_* env vars Laravel's stock
# pgsql connection reads — a container-boot detail, not a redesign of the secret's shape.
if [ -n "$DB_CREDENTIALS_JSON" ]; then
  eval "$(php -r '
    $c = json_decode(getenv("DB_CREDENTIALS_JSON"), true);
    foreach ([
        "DB_HOST" => "/cloudsql/" . $c["connection_name"],
        "DB_PORT" => "5432",
        "DB_DATABASE" => $c["database"],
        "DB_USERNAME" => $c["username"],
        "DB_PASSWORD" => $c["password"],
    ] as $k => $v) {
        printf("export %s=%s\n", $k, escapeshellarg($v));
    }
  ')"
fi

php artisan migrate --force --no-interaction

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
