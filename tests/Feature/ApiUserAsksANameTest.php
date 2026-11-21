<?php

// credential-scope 08 (07's route-first sweep): `GET api/user` returned the caller's record with no decision, so a
// scoped token read it whatever it carried. It now reads through the user policy's `view` on the caller, so the
// permission cascade's fail-closed gate asks a scoped token for `user.view` or `user.{id}.view` (fleet ADR 0006 rule 1),
// as `GET api/v1/me` does. The policy admits any signed-in caller to their own record, so a session and a `*` token are
// unchanged.

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

uses(Tests\TestCase::class, RefreshDatabase::class);

function cs08bApiUserBearer($test, User $user, array $abilities)
{
    Auth::forgetGuards();

    return $test->withoutToken()->withToken($user->createToken('cs08b', $abilities)->plainTextToken);
}

it('answers a * bearer its own record, as before', function () {
    $user = User::factory()->create();

    cs08bApiUserBearer($this, $user, ['*'])->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
});

it('asks the user policy\'s view decision on the caller before answering', function () {
    $user = User::factory()->create();
    $asked = [];
    // a Gate::before that refuses exactly `view` on the caller: the route must consult that decision
    Gate::before(function ($actor, string $ability, array $arguments = []) use (&$asked, $user) {
        $subject = $arguments[0] ?? null;
        if ($ability === 'view' && $subject instanceof User && $subject->is($user)) {
            $asked[] = $ability;

            return false;
        }

        return null;
    });

    cs08bApiUserBearer($this, $user, ['*'])->getJson('/api/user')->assertForbidden();
    expect($asked)->toBe(['view']);
});
