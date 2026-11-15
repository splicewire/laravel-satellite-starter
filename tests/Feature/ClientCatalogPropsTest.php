<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Splicewire\Beam\Localization\Client\ClientCatalogResolver;
use Splicewire\Beam\Localization\Client\LangFingerprint;
use Splicewire\Beam\Localization\Locales;
use Splicewire\Beam\Localization\PseudoLocale;
use Tests\TestCase;

/**
 * localization 16-d (the PHP half; root qitem-20261114165608-e8e3891e), proven on a host: every Inertia page carries the
 * client catalog the React tree reads (`localization: {address, initial}`, shared by laravel-beam-ux), in the request's
 * language, with the host's own lang overrides and a realm's own rung, and marked under the pseudo-locale.
 *
 * The DISCOVERED catalog is a fixture: tests/Fixtures/Localization/discovered-catalog.json is laravel-beam's own
 * self-consistent fixture, copied verbatim. The starter's real discovery comes from the JS collector with the 0.x
 * release, and the provider render (beam-inertia's BeamMessages) is the JS half, PENDING that release.
 */
class ClientCatalogPropsTest extends TestCase
{
    use RefreshDatabase;

    private string $lang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // The HOST's lang tree, with a host override for a JS-only key and the operator realm's own rung for it.
        $this->lang = sys_get_temp_dir().'/starter-client-catalog-'.getmypid().'-'.bin2hex(random_bytes(4));
        foreach (['vendor/schemastud-ui/es', 'vendor/schemastud-ui/es/operator'] as $dir) {
            mkdir($this->lang.'/'.$dir, 0777, true);
        }
        file_put_contents($this->lang.'/vendor/schemastud-ui/es/messages.php', "<?php return ['save' => 'Guardar'];");
        file_put_contents($this->lang.'/vendor/schemastud-ui/es/operator/messages.php', "<?php return ['save' => 'Guardar (operador)'];");
        $this->app->useLangPath($this->lang);
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');

        config([
            'beam.localization.client_catalog' => base_path('tests/Fixtures/Localization/discovered-catalog.json'),
            'beam.localization.supported' => ['en', 'es'],
            'beam.localization.pseudo' => true,
        ]);
        $this->app->forgetScopedInstances();
        @unlink(LangFingerprint::artifactPath());
    }

    /** @return array<string, mixed> the dashboard page's `localization` shared prop, as the client receives it */
    private function localization(string $acceptLanguage): array
    {
        $props = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'), ['Accept-Language' => $acceptLanguage])
            ->assertOk()
            ->viewData('page')['props'];

        return json_decode(json_encode($props['localization'], JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_a_page_carries_the_client_catalog_in_the_requests_language_with_the_hosts_override(): void
    {
        $localization = $this->localization('es');

        $this->assertSame('es', $localization['address']['locale']);
        $this->assertSame(1, $localization['address']['version']);
        $this->assertNotSame('', $localization['address']['hash']);
        $this->assertStringStartsWith('/beam/localization/catalog/es', $localization['address']['url']);

        $this->assertNotNull($localization['initial'], 'a valid discovery yields an initial catalog');
        $this->assertSame('es', $localization['initial']['locale']);
        $save = $localization['initial']['messages']['schemastud-ui::messages.save'];
        $this->assertSame(['Guardar', 'es'], [$save['text'], $save['sourceLocale']], 'the host override reaches the client catalog');
        // A key the host leaves alone keeps its English default, answered by `en`.
        $this->assertSame('Profile updated.', $localization['initial']['messages']['messages.profile_updated']['text']);
    }

    public function test_a_realm_rung_changes_the_line_for_that_realm_only(): void
    {
        $this->app->forgetScopedInstances();
        $resolver = $this->app->make(ClientCatalogResolver::class);

        $this->assertSame('Guardar (operador)', $resolver->resolve('es', 'operator')->messages['schemastud-ui::messages.save']->text);
        $this->assertSame('Guardar', $resolver->resolve('es', 'tenant')->messages['schemastud-ui::messages.save']->text);
        // Each realm's catalog has its own address, so the client takes a new value on a realm switch.
        $this->assertNotSame($resolver->address('es', 'operator')->hash, $resolver->address('es', 'tenant')->hash);
    }

    public function test_the_served_lines_are_marked_under_the_pseudo_locale(): void
    {
        $localization = $this->localization(Locales::PSEUDO);

        $this->assertSame(Locales::PSEUDO, $localization['address']['locale']);
        $this->assertNotNull($localization['initial']);
        foreach ($localization['initial']['messages'] as $key => $line) {
            $this->assertTrue(PseudoLocale::isMarked($line['text']), "{$key}: {$line['text']}");
        }
    }
}
