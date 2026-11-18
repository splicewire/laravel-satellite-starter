<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ux-walkthrough UX-03b (integrator 07:45Z): this starter's shell showed "Laravel" and the Laravel starter kit's logo,
 * because the brand fell back to `config('app.name')` and live `.env` files say APP_NAME=Laravel. The brand is
 * declared in committed config (`config/beam/brand.php`, env optional), so an `APP_NAME=Laravel` deployment still
 * shows its own name, in the shared `brand` prop and in the server-rendered `<title>`.
 */
class ShellBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shell_carries_the_declared_brand_even_with_app_name_laravel(): void
    {
        config(['app.name' => 'Laravel']);

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Satellite', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Satellite</title>', false);
        $response->assertDontSee('<title>Laravel</title>', false);
    }

    /**
     * localization 35 (root ruling 35b): the brand is authored configuration. A host's declared name (BEAM_BRAND_NAME, read into
     * `beam.brand.name`) wins over the committed default, in the shared prop and in the server-rendered `<title>`.
     */
    public function test_a_declared_brand_wins_over_the_committed_default(): void
    {
        config(['app.name' => 'Laravel', 'beam.brand.name' => 'Acme Field Notes']);

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Acme Field Notes', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Acme Field Notes</title>', false);
        $response->assertDontSee('<title>Satellite</title>', false);
    }
}
