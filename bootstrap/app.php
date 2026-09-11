<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Bind spatie's team scope to the signed-in user's current team for the request.
            // WITHOUT THIS LINE THE HOST AUTHORIZES NOTHING, and does so silently: beam-accounts'
            // roles are team-scoped (`roles.team_id`), so with the registrar's team id left null a
            // user resolves ZERO roles, `BaseModelPolicy::viewAny()` finds no `<alias>.view` token,
            // and every cascade-policed resource is denied to its own team owner. Measured here on
            // 2026-09-05: with the permission rows seeded and the team id set the owner's `viewAny`
            // was true in tinker while the live `/frame/manifest` still rendered ONE nav row.
            //
            // Host-side on purpose, and beam-accounts says so in terms — the package ALIASES this
            // middleware and stops ("Add this to the app's `web` group", Http/Middleware/
            // SetCurrentTeamPermissions.php:12). Pushing it into `web` from the provider would
            // silently overwrite the tenant scope at every tenanted host, where
            // beam-tenancy's PermissionsTenancyBootstrapper.php:56 sets the SAME registrar slot to
            // the tenant key. ~/Herd/schemastud does the equivalent through `frame.middleware`.
            'splicewire.team',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
