<?php

namespace App\Providers;

use App\Account\StarterAccountShell;
use App\Beam\RealmRegistry;
use App\Data\SitemapData;
use App\Doctor\OrphanedNavItemAudit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Rushing\PermissionCascade\Contracts\EntitlementResolver;
use Splicewire\Beam\Accounts\Contracts\AccountShellProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the host account-shell provider over beam-accounts' NullAccountShellProvider default,
        // so the packaged <AccountShell> renders real (neutral demo) plan/profile data OOTB.
        $this->app->bind(
            AccountShellProvider::class,
            StarterAccountShell::class,
        );
    }

    /**
     * Bootstrap any application services.
     *
     * NOTE: the sitemap resource is NOT registered here. It is declared entirely by the single
     * `#[ParticleResource]` on {@see SitemapData}, which beam's attributed discovery
     * reflects into the admin manifest at boot (config `frame.discover_paths` points the scan at
     * `app/Data`). Dropping one annotated Data class IS the wiring — no provider line, no second
     * declaration, no handler-map entry.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerSharedMigrations();
        $this->registerAuthoringGates();
        $this->registerDoctorAudits();
    }

    /**
     * Register this HOST's doctor audit into beam-core's aggregation manifest — the same seam every
     * beam-* package uses (the manifest is a singleton bound in beam's register phase and read fresh
     * when `splicewire:beam:doctor` runs, so an app registering in boot always lands). Advisory: an
     * orphaned nav link renders yellow but never turns the run red. Guarded on the manifest being
     * bound so the starter still boots against a beam-core that predates it.
     */
    protected function registerDoctorAudits(): void
    {
        if ($this->app->bound(BeamDoctorManifest::class)) {
            $this->app->make(BeamDoctorManifest::class)->register(
                package: 'app',
                audit: OrphanedNavItemAudit::class,
                gate: false,
            );
        }
    }

    /**
     * Register the authoring gates (theme-entries-and-authoring ticket 02, porting
     * `rushing/audiostud`'s `AppServiceProvider::registerRealmAuthoringGates()`, AUD-02's retired
     * form): the flat global `ux.author` ability resolves through `entitlement:ux.author` — the Gate
     * `beam`'s `registerEntitlementAbilities()` already registers off `config('app.entitlements')`,
     * fed by `laravel-beam-accounts`' `DefaultEntitlementResolver` (ACC-01's realm-grant cascade: an
     * Owner/Admin-held Team's `manage` grant on a realm's root). No `is_staff` — this host never bound
     * its own resolver, so it already rides the package default; only the Gate SURFACE needed fixing.
     *
     * `ux.{realm}.author` reads the resolver's raw key list directly — there is no
     * `entitlement:ux.{realm}.author` Gate (beam only registers abilities for the flat keys in
     * `config('app.entitlements')`, and `ux.{realm}.author` is realm-parameterized, not flat).
     *
     * **Deliberately does NOT fall back to `ux.author` for the per-realm check.**
     * `DefaultEntitlementResolver` composes the coarse `ux.author` key as soon as ANY single realm is
     * granted (mirroring old blanket-staff semantics for a Team holding EVERY realm's grant) — an
     * `ux.{realm}.author = ux.author || ...` shortcut would let a grant on just ONE realm leak
     * authoring into every OTHER realm too, defeating the per-realm grain this Gate exists for. A
     * grantee of every realm still authors every realm (each `ux.{realm}.author` key composes
     * independently); a narrowly-granted principal now correctly stays narrow.
     */
    protected function registerAuthoringGates(): void
    {
        Gate::define('ux.author', fn (User $user): bool => $user->can('entitlement:ux.author'));

        foreach (RealmRegistry::realms() as $realm) {
            Gate::define(
                RealmRegistry::authorAbility($realm),
                fn (User $user): bool => in_array(
                    RealmRegistry::authorAbility($realm),
                    app(EntitlementResolver::class)->entitlementsFor($user),
                    true,
                ),
            );
        }
    }

    /**
     * Register the beam "shared" migrations directory with the default migrator.
     *
     * OOTB-wiring note (frontend-surfaces): beam-ux (and the wider beam estate) publish their
     * `beam_ux_entries` / `sitemaps` / particle migrations as PUBLISH-ONLY stubs into
     * `database/migrations/shared/` — a subdirectory the stock Laravel migrator does NOT scan.
     * In a tenant host, `splicewire/laravel-beam-tenancy` registers this path in both the central
     * and per-tenant passes. This starter is single-tenant (no beam-tenancy), so nothing registers
     * it and `php artisan migrate` silently skips the beam substrate. Registering it here makes the
     * published beam tables migrate on a plain `migrate`.
     */
    protected function registerSharedMigrations(): void
    {
        $shared = database_path('migrations/shared');

        if (is_dir($shared)) {
            $this->loadMigrationsFrom($shared);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
