<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Tests\TestCase;

/**
 * frame-foundation 51: the tokens page writes no URL. Its route hands it the endpoints laravel-beam-accounts builds
 * from its own mounted routes (`TokensEndpoints::props()`), and the page passes them to `createTokensClient`, so the
 * page follows `ACCOUNT_API_ROOT` wherever the host mounts it.
 */
class AccountTokensEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tokens_page_receives_the_mounted_token_routes_as_its_endpoints(): void
    {
        $this->withoutVite();
        $member = User::factory()->create();
        $team = Team::create(['user_id' => $member->id, 'name' => 'Mine', 'personal_team' => true]);
        Membership::create(['team_id' => $team->id, 'user_id' => $member->id, 'role' => Role::Owner->value]);
        $member->fresh()->switchTeam($team);

        $root = '/'.trim(config('beam.accounts.api_root'), '/');

        $this->actingAs($member->fresh())->get(route('account.tokens'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/tokens', shouldExist: false)
                ->where('endpoints', [
                    'index' => "{$root}/tokens",
                    'permissions' => "{$root}/tokens/permissions",
                    'sessionsOthers' => "{$root}/tokens/sessions/others",
                ]));

        // The roster the page lists is reachable at the endpoint it was handed.
        $this->actingAs($member->fresh())->getJson("{$root}/tokens")->assertOk();
    }
}
