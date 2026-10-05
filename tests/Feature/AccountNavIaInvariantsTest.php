<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Ux\Ia\IaInvariantViolation;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * ux-walkthrough UX-06: the account rail (`accountNav`) runs the IA invariants. Its edges into the operator and user
 * realms are UX-12b's listed exception, because until the realm switcher (UX-12a) the operator-seat row is the
 * operator realm's only door; every other breach throws, and the old catch-all no longer hides it.
 */
class AccountNavIaInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('splicewire:beam:ux:seed-nav');
    }

    private function staff(): User
    {
        $staff = User::factory()->create();
        $team = Team::create(['user_id' => $staff->id, 'name' => 'Staff', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $staff->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $staff->fresh();
    }

    /** @return list<string> */
    private function accountHrefs(User $user): array
    {
        $hrefs = [];
        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertInertia(function (Assert $page) use (&$hrefs) {
                $walk = function (array $nodes) use (&$walk, &$hrefs): void {
                    foreach ($nodes as $node) {
                        $hrefs[] = $node['href'] ?? null;
                        $walk($node['children'] ?? []);
                    }
                };
                $walk($page->toArray()['props']['accountNav']['items'] ?? []);
            });

        return $hrefs;
    }

    public function test_a_signed_in_page_renders_with_the_operator_seat_door_for_staff(): void
    {
        $hrefs = $this->accountHrefs($this->staff());

        $this->assertContains('/operator', $hrefs, 'the operator-seat door is the listed exception, not a breach');
        $this->assertContains('/dashboard', $hrefs);
    }

    public function test_a_plain_member_gets_the_rail_without_the_door(): void
    {
        $hrefs = $this->accountHrefs(User::factory()->create());

        $this->assertNotContains('/operator', $hrefs);
        $this->assertContains('/dashboard', $hrefs);
    }

    public function test_an_account_row_that_joins_no_mounted_route_throws_rather_than_vanishing(): void
    {
        BeamUxEntry::query()->where('slug', 'account-team')->update(['segment' => '/account/no-such-page']);

        $this->withoutExceptionHandling();
        $this->expectException(IaInvariantViolation::class);
        $this->expectExceptionMessageMatches('#I4 tenant /account/no-such-page#');

        $this->actingAs(User::factory()->create())->get(route('dashboard'));
    }

    public function test_in_production_a_breaking_row_is_reported_at_error_level_and_the_page_still_renders(): void
    {
        config(['beam.ux.ia.throw' => false]);
        Log::spy();
        BeamUxEntry::query()->where('slug', 'account-team')->update(['segment' => '/account/no-such-page']);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();

        Log::shouldHaveReceived('error')->with(Mockery::on(
            fn (string $message): bool => str_contains($message, 'tenant rail at') && str_contains($message, 'I4 tenant /account/no-such-page'),
        ), Mockery::any())->once();
    }
}
