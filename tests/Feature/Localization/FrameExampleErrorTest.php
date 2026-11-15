<?php

namespace Tests\Feature\Localization;

use App\Localization\FrameExampleError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Splicewire\Beam\Accounts\Teams\TeamProvisioner;
use Splicewire\Beam\Localization\Locales;
use Splicewire\Beam\Localization\PseudoLocale;
use Tests\TestCase;

/**
 * localization 27-W2 (SIGNATURES-27 8d8ec3c6 §2): the example read-schema resolver's refusal of an unknown `purpose` is a coded
 * error. A JSON client gets Laravel's validation body plus the stable code; its English is unchanged; under the pseudo-locale the
 * line is marked (it resolves through the ladder, it is not a literal).
 */
class FrameExampleErrorTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $team = app(TeamProvisioner::class)->personalTeamFor($owner);
        $owner->forceFill(['current_team_id' => $team->getKey()])->save();
        $owner->unsetRelation('roles')->unsetRelation('permissions');
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $owner;
    }

    public function test_an_unknown_purpose_is_refused_with_its_code_and_unchanged_english(): void
    {
        $this->actingAs($this->owner())
            ->getJson('/frame/resources/sitemap/schema?purpose=unknown')
            ->assertUnprocessable()
            ->assertJsonPath('errors.purpose.0', 'Unknown schema purpose [unknown]; expected one of: write, read.')
            ->assertJsonPath('codes.purpose.0', FrameExampleError::UnknownSchemaPurpose->value);
    }

    public function test_the_refusal_is_marked_under_the_pseudo_locale(): void
    {
        config(['beam.localization.pseudo' => true]);
        $this->app->forgetScopedInstances();

        $message = $this->actingAs($this->owner())
            ->getJson('/frame/resources/sitemap/schema?purpose=unknown', ['Accept-Language' => Locales::PSEUDO])
            ->assertUnprocessable()
            ->assertJsonPath('codes.purpose.0', 'unknown_schema_purpose')
            ->json('errors.purpose.0');

        $this->assertTrue(PseudoLocale::isMarked($message), $message);
    }

    public function test_the_case_declares_its_english(): void
    {
        $this->assertSame('Unknown schema purpose [:purpose]; expected one of: write, read.', FrameExampleError::UnknownSchemaPurpose->defaultMessage());
        $this->assertSame('unknown_schema_purpose', FrameExampleError::UnknownSchemaPurpose->code());
    }
}
