<?php

namespace App\Providers;

use App\Data\SitemapData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Schemastud\Frame\Registry\AdminResourceRegistry;

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
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerSitemapResource();
    }

    /**
     * Opt the record leaf (kind A) into Frame with ONE registry line. Reflecting the
     * #[AdminResource] on SitemapData surfaces the `frame/resources/sitemap` editor route so
     * an owner can edit the nav; the SitemapRecord projection then merges into the RootSitemap
     * at runtime (cache-busted on save). Moat-free: needs only schemastud/laravel-frame.
     *
     * Presence-conditional so the starter still boots headless if frame is ever removed.
     */
    protected function registerSitemapResource(): void
    {
        if (! $this->app->bound(AdminResourceRegistry::class)) {
            return;
        }

        $this->app->make(AdminResourceRegistry::class)->registerClass(SitemapData::class);
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
