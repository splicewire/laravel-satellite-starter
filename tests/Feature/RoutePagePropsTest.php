<?php

declare(strict_types=1);

use App\Data\Pages\EntryPageData;
use App\Data\Pages\OperatorDashboardPageData;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\ResponseFactory;

uses(Tests\TestCase::class);

beforeEach(function () {
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
    Schema::create('beam_ux_entries', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('slug');
        $table->string('type');
        $table->string('namespace');
        $table->softDeletes();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
    });
    DB::table('users')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
    DB::table('beam_ux_entries')->insert([
        ['id' => 'realm-one', 'slug' => 'site', 'type' => 'page', 'namespace' => 'realms'],
        ['id' => 'realm-two', 'slug' => 'account', 'type' => 'page', 'namespace' => 'realms'],
    ]);
    config(['beam.ux.namespace' => 'route-props-test']);
});

function routePropsRequest(string $component, ?string $only, int &$staffEvaluations): Request
{
    $headers = ['HTTP_X_INERTIA' => 'true'];
    if ($only !== null) {
        $headers += ['HTTP_X_INERTIA_PARTIAL_COMPONENT' => $component, 'HTTP_X_INERTIA_PARTIAL_DATA' => $only];
    }
    $request = Request::create('/route-props-test', 'GET', server: $headers);
    app()->instance('request', $request);
    $request->setUserResolver(function () use (&$staffEvaluations) {
        $staffEvaluations++;

        return (new User)->forceFill(['name' => 'Operator', 'email' => 'operator@example.test']);
    });
    $request->setLaravelSession(new Store('route-props-test', new ArraySessionHandler(120)));

    return $request;
}

it('declares each route page and preserves initial populated and missing entry payloads', function (string $route, string $component, string $slug, string $dataClass, bool $seeded) {
    $entry = $seeded ? ['id' => 'page-entry', 'slug' => $slug] : null;
    if ($entry !== null) {
        DB::table('beam_ux_entries')->insert($entry + ['type' => 'page', 'namespace' => 'route-props-test']);
    }
    $staffEvaluations = 0;
    $request = routePropsRequest($component, null, $staffEvaluations);
    $factory = new ResponseFactory;
    Inertia::partialMock()->shouldReceive('render')->once()->andReturnUsing(function ($actualComponent, $props) use ($component, $dataClass, $factory) {
        expect($actualComponent)->toBe($component)->and($props)->toBeInstanceOf($dataClass);

        return $factory->render($actualComponent, $props);
    });
    $counts = 0;
    DB::listen(function ($query) use (&$counts) {
        if (str_contains($query->sql, 'count(*)')) {
            $counts++;
        }
    });
    $response = Route::getRoutes()->getByName($route)->bind($request)->run();
    expect($staffEvaluations)->toBe(0)->and($counts)->toBe(0);
    // The entry ref carries `format` and `artifact` beside `{id, slug}` (G2-BEAM-AUTHOR-ENTRY): a save
    // address alone left `/` unreadable and let the canvas open an mdx entry. Both are null for these
    // rows — the fixture inserts no format and compiles no artifact — and null is the honest answer
    // ("the host did not say" / "never authored"), which is exactly what the client must receive.
    $expected = ['entry' => $entry === null ? null : $entry + ['format' => null, 'artifact' => null]];
    if ($route === 'operator.home') {
        $expected += ['staff' => ['name' => 'Operator', 'email' => 'operator@example.test'], 'stats' => ['users' => 3, 'sitemaps' => 2, 'entries' => $seeded ? 3 : 2]];
    }
    expect($response->toResponse($request)->getData(true)['props'])->toBe($expected);
    expect($staffEvaluations)->toBe($route === 'operator.home' ? 2 : 0);
    expect($counts)->toBe($route === 'operator.home' ? 3 : 0);
})->with([
    'site' => ['home', 'site/home', 'home', EntryPageData::class],
    'account' => ['dashboard', 'account/home', 'dashboard', EntryPageData::class],
    'operator' => ['operator.home', 'operator/dashboard', 'operator-dashboard', OperatorDashboardPageData::class],
])->with(['unseeded' => false, 'seeded' => true]);

it('evaluates only the selected operator closure on partial reloads', function (string $only, array $expected, int $expectedStaff, int $expectedCounts) {
    $staffEvaluations = 0;
    $request = routePropsRequest('operator/dashboard', $only, $staffEvaluations);
    Inertia::swap(new ResponseFactory);
    $counts = 0;
    DB::listen(function ($query) use (&$counts) {
        if (str_contains($query->sql, 'count(*)')) {
            $counts++;
        }
    });
    $response = Route::getRoutes()->getByName('operator.home')->bind($request)->run();
    expect($staffEvaluations)->toBe(0)->and($counts)->toBe(0);
    expect($response->toResponse($request)->getData(true)['props'])->toBe($expected);
    expect($staffEvaluations)->toBe($expectedStaff)->and($counts)->toBe($expectedCounts);
})->with([
    'entry only skips both' => ['entry', ['entry' => null], 0, 0],
    'staff only skips stats' => ['staff', ['staff' => ['name' => 'Operator', 'email' => 'operator@example.test']], 2, 0],
    'stats only skips staff' => ['stats', ['stats' => ['users' => 3, 'sitemaps' => 2, 'entries' => 2]], 0, 3],
]);

it('projects nested operator props as optional objects rather than scalar lazy implementations', function () {
    $schema = app(Schemastud\DataSchemas\Generators\Generator::class)->generate(new ReflectionClass(OperatorDashboardPageData::class));
    expect($schema['required'])->not->toContain('staff', 'stats');
    expect($schema['properties']['staff']['readOnly'])->toBeTrue();
    expect($schema['properties']['stats']['readOnly'])->toBeTrue();
    // Resolve the generator's relative id under a retrieval base without changing its document.
    $document = json_decode(json_encode([
        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
        '$id' => 'https://route-props.test/operator.schema.json',
        'allOf' => [$schema],
    ], JSON_THROW_ON_ERROR));
    $validator = new Opis\JsonSchema\Validator;
    $valid = ['entry' => null, 'staff' => ['name' => 'Operator', 'email' => 'operator@example.test'], 'stats' => ['users' => 3, 'sitemaps' => 2, 'entries' => 2]];
    expect($validator->validate(json_decode(json_encode($valid)), $document)->isValid())->toBeTrue();
    expect($validator->validate((object) ['entry' => null], $document)->isValid())->toBeTrue();
    foreach (['staff', 'stats'] as $property) {
        foreach (['scalar', 42, null] as $invalid) {
            $payload = $valid;
            $payload[$property] = $invalid;
            expect($validator->validate(json_decode(json_encode($payload)), $document)->isValid())->toBeFalse();
        }
    }
});
