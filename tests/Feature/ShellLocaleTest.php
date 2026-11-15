<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Localization\Locales;
use Splicewire\Beam\Localization\PseudoLocale;
use Tests\TestCase;

/**
 * localization 11, proven on a host: the shell around a page reads in the request's language. Under the pseudo-locale,
 * every DECLARED display field the shell shares is marked:
 *
 * - `brand`: tagline, passkey copy and title template, all beam's explicit keys. `name` is a proper noun, not keyed.
 * - `realms`: each realm's label, and Back.
 * - `realmManifest`: each realm's title, and any capability title and upsell copy.
 *
 * `nav` and `accountNav` are authored, persisted content (BeamUxEntry titles): localization 37's, not this ticket's.
 * Their `Str::headline(slug)` fallback is deliberately left unmarked (coord-lead ruling 21:45Z).
 */
class ShellLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> the dashboard page's shared props */
    private function props(array $headers = []): array
    {
        $this->withoutVite();

        return $this->actingAs(User::factory()->create())
            ->get(route('dashboard'), $headers)
            ->assertOk()
            ->viewData('page')['props'];
    }

    /** @return list<string> `path: value` for every display string under `$node` that is not marked */
    private function plainEnglish(mixed $node, string $path, array $fields): array
    {
        if (! is_array($node)) {
            return [];
        }
        $found = [];
        foreach ($node as $key => $value) {
            $here = $path.'.'.$key;
            if (in_array($key, $fields, true) && is_string($value) && $value !== '' && ! PseudoLocale::isMarked($value)) {
                $found[] = "{$here}: {$value}";
            }
            $found = [...$found, ...$this->plainEnglish($value, $here, $fields)];
        }

        return $found;
    }

    public function test_the_shells_declared_text_is_marked_under_the_pseudo_locale(): void
    {
        config(['beam.localization.pseudo' => true]);
        $props = $this->props(['Accept-Language' => Locales::PSEUDO]);

        $brand = $props['brand'];
        $this->assertTrue(PseudoLocale::isMarked($brand['passkeyCopy']), $brand['passkeyCopy']);
        foreach (['tagline', 'titleTemplate'] as $field) {
            if (($brand[$field] ?? null) !== null) {
                $this->assertTrue(PseudoLocale::isMarked($brand[$field]), "{$field}: {$brand[$field]}");
            }
        }
        $this->assertFalse(PseudoLocale::isMarked($brand['name']), 'the brand name is a proper noun, never keyed');

        $this->assertNotEmpty($props['realms']['realms']);
        $this->assertSame([], $this->plainEnglish($props['realms'], 'realms', ['label']));
        $this->assertNotEmpty($props['realmManifest']);
        $this->assertSame([], $this->plainEnglish($props['realmManifest'], 'realmManifest', ['title', 'cta']));
    }

    public function test_the_hosts_configured_realm_label_and_brand_render_unchanged_in_english(): void
    {
        $props = $this->props();

        $tenant = collect($props['realms']['realms'])->firstWhere('key', 'tenant');
        $this->assertSame('App', $tenant['label']);
        $this->assertSame(config('beam.brand.name') ?: config('app.name'), $props['brand']['name']);
        foreach (collect($props['realms']['realms'])->pluck('label') as $label) {
            $this->assertFalse(PseudoLocale::isMarked((string) $label), (string) $label);
        }
    }
}
