<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\PermissionCascade\Contracts\AccessGrant;
use Splicewire\Beam\Accounts\Enums\Role;
use Splicewire\Beam\Accounts\Models\Membership;
use Splicewire\Beam\Accounts\Models\Team;
use Splicewire\Beam\Accounts\Sharing\AccessGrants;
use Splicewire\Beam\Localization\Locales;
use Splicewire\Beam\Localization\PseudoLocale;
use Splicewire\Beam\Ux\Models\BeamUxEntry;
use Tests\TestCase;

/**
 * localization 08, proven on a host: under the pseudo-locale, no display field of a realm's frame manifest is plain
 * English. Every `label` and `singularLabel` the `resources` and `contexts` blocks carry (resource labels, singulars,
 * action labels, column labels) came through the catalog. `nav` and `routeContext` are nav projection (ticket 11).
 */
class FrameManifestLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** The operator cascade {@see OperatorFrameManifestTest} grants. */
    private function operator(): User
    {
        $user = User::factory()->create();
        $team = Team::create(['user_id' => $user->id, 'name' => 'Operators', 'personal_team' => false]);
        Membership::create(['team_id' => $team->id, 'user_id' => $user->id, 'role' => Role::Owner->value]);
        app(AccessGrants::class)->share(BeamUxEntry::rootFor('operator'), $team, AccessGrant::ABILITY_MANAGE);

        return $user->fresh();
    }

    /** @return list<string> `path: value` for every display string that is not marked */
    private function plainEnglish(mixed $node, string $path = ''): array
    {
        if (! is_array($node)) {
            return [];
        }
        $found = [];
        foreach ($node as $key => $value) {
            $here = $path === '' ? (string) $key : $path.'.'.$key;
            if (in_array($key, ['label', 'singularLabel'], true) && is_string($value) && $value !== '' && ! PseudoLocale::isMarked($value)) {
                $found[] = "{$here}: {$value}";
            }
            $found = [...$found, ...$this->plainEnglish($value, $here)];
        }

        return $found;
    }

    public function test_no_resource_or_context_display_field_is_plain_english_under_the_pseudo_locale(): void
    {
        config(['beam.localization.pseudo' => true]);

        $manifest = $this->actingAs($this->operator())
            ->getJson('/operator/frame/manifest', ['Accept-Language' => Locales::PSEUDO])
            ->assertOk()
            ->assertHeader('Content-Language', Locales::PSEUDO)
            ->json();

        $this->assertNotEmpty($manifest['resources'], 'The operator manifest projected no resource.');
        $this->assertSame([], $this->plainEnglish(['resources' => $manifest['resources'], 'contexts' => $manifest['contexts']]));
    }

    public function test_the_english_manifest_is_unchanged_with_the_pseudo_locale_off(): void
    {
        $manifest = $this->actingAs($this->operator())->getJson('/operator/frame/manifest')->assertOk()->json();
        $labels = array_column(array_column($manifest['resources'], 'nav'), 'label');

        $this->assertNotEmpty($labels);
        foreach ($labels as $label) {
            $this->assertFalse(PseudoLocale::isMarked((string) $label), (string) $label);
        }
    }
}
