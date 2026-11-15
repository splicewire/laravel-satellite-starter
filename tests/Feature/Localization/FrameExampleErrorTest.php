<?php

namespace Tests\Feature\Localization;

use App\Frame\Examples\SitemapReadSchemaResolver;
use App\Localization\FrameExampleError;
use Illuminate\Http\Request;
use Schemastud\Frame\Contracts\ResourceRegistry;
use Splicewire\Beam\Localization\CodedValidationException;
use Splicewire\Beam\Localization\Locales;
use Splicewire\Beam\Localization\PseudoLocale;
use Tests\TestCase;

/**
 * localization 27-W2 (SIGNATURES-27 8d8ec3c6 §2): the example read-schema resolver's refusal of an unknown `purpose` is a coded
 * error. It is exercised on the RESOLVER: over HTTP, laravel-frame's FrameResourceController@schema validates `purpose` itself
 * (Laravel's `in` rule: "The selected purpose is invalid.") before any host resolver is asked, so the resolver's refusal guards
 * every other caller of the ResourceSchemaResolver chain (44c leg finding). A JSON client of the coded refusal gets Laravel's
 * validation body plus the stable code; its English is unchanged; under the pseudo-locale the line is marked.
 */
class FrameExampleErrorTest extends TestCase
{
    private function refusal(): CodedValidationException
    {
        $definition = app(ResourceRegistry::class)->get('sitemap');
        try {
            (new SitemapReadSchemaResolver)->schema($definition, Request::create('/?purpose=unknown'));
        } catch (CodedValidationException $e) {
            return $e;
        }
        $this->fail('an unknown purpose must be refused with a coded validation error');
    }

    public function test_an_unknown_purpose_is_refused_with_its_code_and_unchanged_english(): void
    {
        $e = $this->refusal();

        $this->assertSame(['purpose' => ['Unknown schema purpose [unknown]; expected one of: write, read.']], $e->errors());
        $this->assertSame(['purpose' => [FrameExampleError::UnknownSchemaPurpose->value]], $e->codes());

        $json = $e->render(Request::create('/', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
        $this->assertNotNull($json);
        $this->assertSame('unknown_schema_purpose', $json->getData(true)['codes']['purpose'][0]);
    }

    public function test_the_refusal_is_marked_under_the_pseudo_locale(): void
    {
        config(['beam.localization.pseudo' => true]);
        app()->setLocale(Locales::PSEUDO);

        $message = $this->refusal()->errors()['purpose'][0];

        $this->assertTrue(PseudoLocale::isMarked($message), $message);
    }

    public function test_the_case_declares_its_english(): void
    {
        $this->assertSame('Unknown schema purpose [:purpose]; expected one of: write, read.', FrameExampleError::UnknownSchemaPurpose->defaultMessage());
        $this->assertSame('unknown_schema_purpose', FrameExampleError::UnknownSchemaPurpose->code());
    }
}
