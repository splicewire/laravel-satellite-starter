<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Localization\Locales;
use Splicewire\Beam\Localization\PseudoLocale;
use Tests\TestCase;

/**
 * localization 06, proven on a host: a request is answered in its resolved language, and both the response
 * (`Content-Language`) and the document (`<html lang>`) say so. This starter opts in to the localized HTTP profile in
 * `config/beam/localization.php`; the pseudo-locale stands in for a second language until one ships (ticket 39).
 */
class RequestLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['beam.localization.pseudo' => true]);

        // AHEAD of the host's own routes: the public-entry catch-all (`{path}`) matches first otherwise.
        $existing = Route::getRoutes();
        Route::setRoutes(new RouteCollection);
        // A framework-shipped line, so the probe reads the catalog and not a literal.
        Route::middleware('web')->get('__locale-probe', fn () => response(__('auth.failed')));
        foreach ($existing->getRoutes() as $route) {
            Route::getRoutes()->add($route);
        }
    }

    public function test_a_supported_language_is_answered_in_and_the_response_and_document_say_so(): void
    {
        $response = $this->get(route('login'), ['Accept-Language' => Locales::PSEUDO.', en;q=0.5'])
            ->assertOk()
            ->assertHeader('Content-Language', Locales::PSEUDO);

        $this->assertStringContainsString('<html lang="'.Locales::PSEUDO.'"', $response->getContent());
        $this->assertStringContainsStringIgnoringCase('Accept-Language', (string) $response->headers->get('Vary'));
    }

    public function test_an_unsupported_language_falls_to_the_default(): void
    {
        $response = $this->get(route('login'), ['Accept-Language' => 'ja, de;q=0.8'])
            ->assertOk()
            ->assertHeader('Content-Language', 'en');

        $this->assertStringContainsString('<html lang="en"', $response->getContent());
    }

    public function test_text_renders_in_the_resolved_language_and_the_host_locale_is_restored(): void
    {
        $marked = $this->get('/__locale-probe', ['Accept-Language' => Locales::PSEUDO])->assertOk()->getContent();
        $plain = $this->get('/__locale-probe', ['Accept-Language' => 'en'])->assertOk()->getContent();

        $this->assertTrue(PseudoLocale::isMarked($marked), $marked);
        $this->assertSame('These credentials do not match our records.', $plain);
        $this->assertSame('en', app()->getLocale());
    }
}
