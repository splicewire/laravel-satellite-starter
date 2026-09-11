<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\WithEnvironmentVariable;
use Splicewire\Beam\Ux\Frame\FrameNavContribution;
use Splicewire\Beam\Ux\Frame\RouteContextProjector;
use Tests\TestCase;

/**
 * The tenant frame nav is NAVIGABLE — every href the server projects is a mounted route that serves a
 * real page component.
 *
 * ⚠️ **The instrument here is deliberately the route table plus the rendered Inertia component, and
 * neither alone.** Before this suite existed the manifest was correct, the nav hrefs were correct, and
 * every one of them 404'd: `/frame/manifest` returning two populated seats cannot distinguish a wired
 * front end from an unwired one, which is exactly the failure mode this asserts against. Asking the
 * projector for the hrefs and then asking the HTTP kernel for each one is two differently-shaped
 * instruments; asserting the manifest twice would be one.
 *
 * The hrefs are read from {@see RouteContextProjector::hrefs()} rather than listed here on purpose: a
 * resource added to `config('frame.realms')['tenant']` must make this suite fail if `routes/web.php`
 * does not mount it.
 */
class FrameConsoleRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `/schemas/{id}` is NOT asserted, and the omission is a measured finding rather than a gap in the
     * test: `schemas/{path}` is already mounted by `rushing/laravel-data-schemas`
     * (`data-schemas.document`) and claims that URL. The manifest declares a `schemas.edit` client route
     * at `/schemas/:id` and this host cannot serve it without shadowing a package route — so the
     * manifest and the host genuinely disagree about that one path, and pretending otherwise here would
     * hide it.
     *
     * @var list<string>
     */
    private const SHADOWED_BY_A_PACKAGE_ROUTE = ['/schemas/:id'];

    public function test_every_projected_tenant_href_serves_the_frame_console(): void
    {
        $this->actingAs(User::factory()->create());

        $hrefs = array_diff(
            app(RouteContextProjector::class)->hrefs('tenant'),
            self::SHADOWED_BY_A_PACKAGE_ROUTE
        );

        $this->assertNotEmpty($hrefs, 'The tenant realm must project hrefs, or this suite proves nothing.');

        foreach ($hrefs as $routeName => $href) {
            $url = str_replace(':id', '1', $href);

            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('frame/console'));
        }
    }

    public function test_every_nav_seat_href_serves_the_frame_console(): void
    {
        $this->actingAs(User::factory()->create());

        $tree = app(FrameNavContribution::class)->contributeNav('tenant')['nav'];
        $hrefs = [];

        foreach ($tree['items'] ?? [] as $section) {
            $hrefs[] = $section['href'] ?? null;

            foreach ($section['children'] ?? [] as $child) {
                $hrefs[] = $child['href'] ?? null;
            }
        }

        $hrefs = array_values(array_filter($hrefs));

        $this->assertNotEmpty($hrefs, 'The tenant nav must have seats, or this suite proves nothing.');

        foreach ($hrefs as $href) {
            $this->get($href)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('frame/console'));
        }
    }

    /**
     * The measured half of {@see SHADOWED_BY_A_PACKAGE_ROUTE}: the console mount must NOT steal
     * `/schemas/{path}` from `schemastud/laravel-data-schemas`. Excluding the path from the suite above
     * says "we do not serve it"; this says "and we did not break the route that does" — which is the
     * assertion that would actually fail if route ordering moved.
     */
    // The starter deliberately has no default authority. Declare this test's serving prerequisite
    // before providers boot; relying on a developer's .env leaves canonical installs with no door.
    #[WithEnvironmentVariable('SCHEMA_BASE_URI', 'https://starter.test/schemas')]
    public function test_the_console_mount_does_not_shadow_the_data_schemas_document_route(): void
    {
        $this->actingAs(User::factory()->create());

        $route = app('router')->getRoutes()->match(
            Request::create('/schemas/some-document', 'GET')
        );

        $this->assertSame('data-schemas.document', $route->getName());
    }

    public function test_a_guest_is_sent_to_login_rather_than_to_the_console(): void
    {
        $this->get('/beam-ux-entry')->assertRedirect(route('login'));
    }

    public function test_an_unclaimed_path_still_falls_through_to_the_public_entry_renderer(): void
    {
        // The console mount is a catch-all constrained to the projected segments. If that constraint
        // were dropped it would swallow `Route::beamUxSite()`'s `{path}` renderer, taking every
        // authored page with it — a failure the console's own routes could never show.
        $this->actingAs(User::factory()->create());

        $this->get('/definitely-not-a-frame-segment')->assertNotFound();
    }
}
