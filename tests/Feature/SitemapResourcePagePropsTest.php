<?php

declare(strict_types=1);

use App\Data\Pages\SitemapResourcePageData;
use App\Data\SitemapData;
use App\Http\Controllers\SitemapResourceController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Schemastud\Frame\Registry\NavMetadata;
use Schemastud\Frame\Registry\ResourceDefinition;
use Splicewire\Beam\Particle\ParticleResourceRegistry;

uses(Tests\TestCase::class);

it('declares the whole sitemap page without changing the Inertia response', function (string $variant) {
    $makeDefinition = fn () => $variant === 'registered'
        ? app(ParticleResourceRegistry::class)->definition('sitemap')
        : new ResourceDefinition(
            key: 'sitemap', model: null, data: SitemapData::class, creatable: false,
            query: null, editData: null, policy: null, form: 'bare', nav: new NavMetadata('Sitemap'),
        );
    $definition = $makeDefinition();
    $legacyDefinition = $makeDefinition();
    if ($variant === 'omitted') {
        // Exclusions are consumed during transformation: each response needs its own instance.
        $definition->except('policy');
        $legacyDefinition->except('policy');
    }
    $registry = Mockery::mock(ParticleResourceRegistry::class);
    $registry->shouldReceive('definition')->once()->with('sitemap')->andReturn($definition);
    $request = Request::create('/frame/resources/sitemap', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
    $factory = new ResponseFactory;
    $legacy = $factory->render('frame/resource', ['resource' => $legacyDefinition])->toResponse($request);
    Inertia::partialMock()->shouldReceive('render')->once()->andReturnUsing(function ($component, $props) use ($factory, $definition) {
        expect($component)->toBe('frame/resource')
            ->and($props)->toBeInstanceOf(SitemapResourcePageData::class)
            ->and($props->resource)->toBe($definition);

        return $factory->render($component, $props);
    });

    $response = (new SitemapResourceController)($registry)->toResponse($request);

    expect($response->getStatusCode())->toBe($legacy->getStatusCode())
        ->and($response->headers->get('X-Inertia'))->toBe($legacy->headers->get('X-Inertia'))
        ->and($response->getData(true))->toBe($legacy->getData(true));
    $resource = $response->getData(true)['props']['resource'];
    if ($variant !== 'registered') {
        expect($resource['model'])->toBeNull()
            ->and($resource['layout'])->toBeNull()
            ->and($resource['nav']['routeName'])->toBeNull()
            ->and($resource['creatable'])->toBeFalse();
    }
    if ($variant === 'omitted') {
        expect($resource)->not->toHaveKey('policy');
    }
})->with(['registered', 'nullable defaults', 'omitted']);

it('preserves selective partial reloads without evaluating unrelated shared props', function () {
    $registry = app(ParticleResourceRegistry::class);
    $request = Request::create('/frame/resources/sitemap', 'GET', server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'frame/resource',
        'HTTP_X_INERTIA_PARTIAL_DATA' => 'resource',
    ]);
    $evaluations = 0;
    $factory = new ResponseFactory;
    $factory->share('unrelated', function () use (&$evaluations) {
        $evaluations++;

        return 'must remain unevaluated';
    });
    Inertia::swap($factory);
    $legacy = $factory->render('frame/resource', ['resource' => $registry->definition('sitemap')])->toResponse($request);
    $response = (new SitemapResourceController)($registry)->toResponse($request);

    expect($response->getData(true))->toBe($legacy->getData(true))
        ->and(array_keys($response->getData(true)['props']))->toBe(['resource'])
        ->and($evaluations)->toBe(0);
});

it('preserves the registry exception instead of turning a missing resource into null props', function () {
    $exception = new InvalidArgumentException('No frame resource registered for key [sitemap].');
    $registry = Mockery::mock(ParticleResourceRegistry::class);
    $registry->shouldReceive('definition')->once()->with('sitemap')->andThrow($exception);
    Inertia::shouldReceive('render')->never();

    expect(fn () => (new SitemapResourceController)($registry))->toThrow($exception);
});
