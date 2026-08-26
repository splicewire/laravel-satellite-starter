<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PageEntryRef;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Splicewire\Beam\Ux\Type\UxType;
use Tests\TestCase;

/**
 * The entry-body transport, as this starter now mounts it: two id-addressed particle OPERATIONS on the
 * `beam-ux-entry` resource (ADR-0214 §1), replacing the slug-addressed `Route::beamUxEntries()` macro.
 *
 * beam-docs-satellite ticket 40 wrote this test because there was none — anywhere. The macro was retired
 * from two live hosts on the strength of an ADR clause that said the starters did not call it, and all
 * three called it on the same line of the same file. Four consecutive undercounts of this transport's
 * consumers happened with a green suite every time, because nothing in any repo asserted where the
 * transport was mounted or that anything reached it.
 */
class EntryBodyTransportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Saving a body writes through beam-ux's DISK MIRROR (`config/beam/ux.php` → the `beam-ux` disk,
     * rooted at `resources/beam-ux/`), which is a TRACKED directory in this repo. Without this the
     * round-trip test below leaves `resources/beam-ux/starter/page/round-trip.tsx` in the working tree
     * — a test that dirties the repo it runs in. Faking the disk redirects the mirror into a temp root.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('beam.ux.storage.mirror_disk', 'beam-ux'));
    }

    private function author(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'A Team', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('site'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    private function pageEntry(string $slug): BeamUxEntry
    {
        return BeamUxEntry::create([
            'slug' => $slug,
            'title' => $slug,
            'type' => UxType::Page,
            'namespace' => config('beam.ux.namespace', 'starter'),
        ]);
    }

    /**
     * The retired macro leaves no trace. A route file calling a macro that is not registered throws at
     * BOOT, before any route is served — which is exactly what `laravel-satellite-starter`'s log
     * recorded on 2026-08-21 — so this is a boot assertion, not a routing one.
     */
    public function test_the_slug_addressed_macro_is_not_mounted(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('beam.ux.entries.body.show'));
        $this->assertNull(Route::getRoutes()->getByName('beam.ux.entries.body.update'));
    }

    public function test_the_two_id_addressed_operations_are_mounted(): void
    {
        $show = Route::getRoutes()->getByName('beam-ux-entry.op.body');
        $save = Route::getRoutes()->getByName('beam-ux-entry.op.save-body');

        $this->assertNotNull($show);
        $this->assertNotNull($save);
        // The read mounts GET (ADR-0214 §4); `particleOp` defaults to POST regardless of kind.
        $this->assertContains('GET', $show->methods());
        $this->assertContains('POST', $save->methods());
    }

    public function test_an_author_round_trips_an_entry_body_by_id(): void
    {
        $entry = $this->pageEntry('round-trip');
        $doc = [['kind' => 'block', 'name' => 'p', 'isComponent' => false, 'dynamic' => false, 'props' => [], 'children' => []]];

        $this->actingAs($this->author())
            ->postJson(route('beam-ux-entry.op.save-body', ['id' => $entry->getKey()]), ['body' => $doc])
            ->assertSuccessful();

        $this->actingAs($this->author())
            ->getJson(route('beam-ux-entry.op.body', ['id' => $entry->getKey()]))
            ->assertSuccessful()
            ->assertJsonPath('data.body.0.name', 'p');
    }

    /**
     * The read declares `input: false`, so a GET carrying ANY query key is a 422. The retired
     * `?namespace=` disambiguator is not merely ignored — it fails loudly, which is the point of
     * addressing by id (ADR-0214 §2).
     */
    public function test_the_read_refuses_a_query_string(): void
    {
        $entry = $this->pageEntry('no-query');

        $this->actingAs($this->author())
            ->getJson(route('beam-ux-entry.op.body', ['id' => $entry->getKey()]).'?namespace=starter')
            ->assertStatus(422);
    }

    /**
     * The server is the only party that can bind a page to a row by id — no compile-time frontend map
     * can carry a per-database uuid. This is what replaced the Mainframe host's `componentSlugFallback`
     * guess at this starter.
     */
    public function test_the_home_page_shares_its_entry_ref_by_id(): void
    {
        $entry = $this->pageEntry('home');

        $this->assertSame(
            ['id' => (string) $entry->getKey(), 'slug' => 'home'],
            PageEntryRef::for('home'),
        );

        $this->get('/')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('entry.id', (string) $entry->getKey()));
    }

    public function test_a_page_entry_ref_is_null_when_the_row_was_never_seeded(): void
    {
        $this->assertNull(PageEntryRef::for('never-seeded'));
    }
}
