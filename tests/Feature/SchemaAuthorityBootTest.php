<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The app BOOTS, registering every route, under each schema authority it is meant to run with (launch 70).
 *
 * The data-schemas door is registered in its provider's boot, before any route file, from data-schemas.base_uri
 * (SCHEMA_BASE_URI). Unset, the shipped default (.env.example), mounts no door; the documented '<origin>/schemas'
 * mounts it at `schemas/{path}`. A bare origin made it a root catch-all that shadowed the beam package API roots
 * and failed the boot ("API root [beam/accounts] is shadowed by earlier catch-all [data-schemas.document]");
 * data-schemas refuses that shape itself now. These tests guard the BOOT under the supported shapes; they pass with
 * and without that package fix.
 *
 * The authority is set in CONFIG the moment configuration loads, not through the env: once a host's .env has been
 * loaded in this process, Laravel's env repository reloads it over any value a test sets (see TestCase), so a
 * developer's own SCHEMA_BASE_URI would otherwise decide what these tests boot with. No default is turned on: the
 * committed schema projections are generated with the authority unset (SchemaArtifactDriftTest).
 */
class SchemaAuthorityBootTest extends TestCase
{
    /** Test method => the data-schemas.base_uri its app boots with (null = unset, the shipped default). */
    private const AUTHORITIES = [
        'test_the_app_boots_with_no_schema_authority' => null,
        'test_the_app_boots_with_a_path_shaped_authority' => 'http://localhost/schemas',
    ];

    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $authority = self::AUTHORITIES[$this->name()] ?? null;

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app) use ($authority): void {
            $app['config']->set('data-schemas.base_uri', $authority);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    private static function named(string $name): ?RouteDefinition
    {
        return collect(Route::getRoutes()->getRoutes())->first(fn (RouteDefinition $route) => $route->getName() === $name);
    }

    public function test_the_app_boots_with_no_schema_authority(): void
    {
        $this->assertNull(config('data-schemas.base_uri'), 'the authority this test boots with must be unset');
        $this->assertNull(self::named('data-schemas.document'), 'no authority, no door');
        $this->assertNotNull(self::named('beam.accounts.invitations.index'), 'the accounts API root must mount: it is what a root schema catch-all shadowed');
    }

    public function test_the_app_boots_with_a_path_shaped_authority(): void
    {
        $door = self::named('data-schemas.document');
        $this->assertNotNull($door, 'a declared authority must mount its door');
        $this->assertSame('schemas/{path}', $door->uri());
        $this->assertNull($door->getDomain(), 'a path-shaped door is not domain-constrained');
        $this->assertNotNull(self::named('beam.accounts.invitations.index'), 'the accounts API root must mount: it is what a root schema catch-all shadowed');
    }
}
