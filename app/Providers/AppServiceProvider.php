<?php

namespace App\Providers;

use App\Data\SitemapData;
use App\Doctor\OrphanedNavItemAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Splicewire\Beam\Doctor\BeamDoctorManifest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
