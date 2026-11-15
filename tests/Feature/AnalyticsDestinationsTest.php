<?php

namespace Tests\Feature;

use App\Analytics\StarterAnalyticsDestinations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Analytics\Contracts\AnalyticsDestinations;
use Splicewire\Beam\Analytics\Destinations\DestinationLinks;
use Splicewire\Beam\Analytics\Destinations\NoDestinations;
use Splicewire\Beam\Analytics\Scope\AnalyticsScope;
use Splicewire\Beam\Realm\RealmRegistry;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * analytics-area 18 AMEND (root's ruling (1), 2026-11-13 14:19:20Z): this host binds the analytics destination port to its own
 * site editor. It has the same front door as the tower starter: `Route::beamUxHome('site/home')` (routes/web.php) registers
 * `home` on `/`, the packaged home page the in-place visual editor addresses, whose editor operations (EditorRoutes, behind
 * auth + verified) each declare `ux.author`. So site-editor = `home` + `ux.author`, on the install side only. It has no Site
 * settings page and no tenant-side editor, so it answers nothing else, and nothing in tenant scope.
 *
 * A destination adds a link to a figure the reader can already see; it never decides who sees the area.
 */
class AnalyticsDestinationsTest extends TestCase
{
    use RefreshDatabase;

    /** A central author: an Owner of a team holding `manage` on every realm's root entry (RealmAuthoringGatesTest's shape). */
    private function author(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'Authors', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        foreach (array_keys(app(RealmRegistry::class)->all()) as $realm) {
            app(AccessGrants::class)->share(BeamUxEntry::rootFor($realm), $team, AccessGrant::ABILITY_MANAGE);
        }

        return $user->fresh();
    }

    private function tenantScope(): AnalyticsScope
    {
        return AnalyticsScope::tenant('acme');
    }

    public function test_the_host_binds_the_destination_port(): void
    {
        $this->assertInstanceOf(StarterAnalyticsDestinations::class, app(AnalyticsDestinations::class));
    }

    public function test_the_site_editor_is_the_front_door_behind_ux_author_on_the_install_side(): void
    {
        $editor = app(AnalyticsDestinations::class)->destination(AnalyticsDestinations::SITE_EDITOR, AnalyticsScope::install());

        $this->assertNotNull($editor);
        $this->assertSame('home', $editor->routeName);
        $this->assertSame('ux.author', $editor->ability);
        $this->assertSame([], $editor->parameters);
    }

    public function test_it_answers_nothing_it_has_no_page_for(): void
    {
        $port = app(AnalyticsDestinations::class);

        $this->assertNull($port->destination(AnalyticsDestinations::SITE_EDITOR, $this->tenantScope()), 'no tenant-side editor');
        $this->assertNull($port->destination(AnalyticsDestinations::SITE_SETTINGS, AnalyticsScope::install()), 'no Site settings page');
        $this->assertNull($port->destination(AnalyticsDestinations::SITE_SETTINGS, $this->tenantScope()));
        $this->assertNull($port->destination(AnalyticsDestinations::TRAFFIC_SOURCES, AnalyticsScope::install()));
        $this->assertNull($port->destination('no-such-destination', AnalyticsScope::install()));
    }

    public function test_the_site_editor_link_draws_for_an_author_and_not_for_anyone_else(): void
    {
        $links = app(DestinationLinks::class);
        $author = $this->author();
        $member = User::factory()->create();
        $this->assertTrue($author->can('ux.author'), 'control: the author passes ux.author');
        $this->assertFalse($member->can('ux.author'), 'control: a plain user does not');

        $this->actingAs($author);
        $this->assertSame('/', $links->href(AnalyticsDestinations::SITE_EDITOR, AnalyticsScope::install()));
        $this->assertNull($links->href(AnalyticsDestinations::SITE_EDITOR, $this->tenantScope()), 'never in tenant scope');

        $this->actingAs($member);
        $this->assertNull($links->href(AnalyticsDestinations::SITE_EDITOR, AnalyticsScope::install()));

        auth()->logout();
        $this->assertNull($links->href(AnalyticsDestinations::SITE_EDITOR, AnalyticsScope::install()), 'a guest');
    }

    public function test_the_binding_never_widens_who_sees_the_area(): void
    {
        $author = $this->author();
        $abilities = ['entitlement:analytics.view', 'analytics.view'];
        $with = array_map(fn (string $a): bool => Gate::forUser($author)->allows($a), $abilities);

        $this->app->bind(AnalyticsDestinations::class, NoDestinations::class);
        $without = array_map(fn (string $a): bool => Gate::forUser($author)->allows($a), $abilities);

        $this->assertSame($without, $with, 'the section gates answer the same with the host binding as with none');
    }
}
