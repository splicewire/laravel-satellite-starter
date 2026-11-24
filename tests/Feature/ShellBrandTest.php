<?php

namespace Tests\Feature;

use Dotenv\Repository\RepositoryBuilder;
use Dotenv\Repository\RepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\TestCase;

/**
 * localization 49: the shared shell brand follows APP_NAME when BEAM_BRAND_NAME is unset, guards Laravel's generic
 * name, and honors a nonblank explicit BEAM_BRAND_NAME.
 */
class ShellBrandTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{0: bool, 1: ?string}> */
    private static array $spaceEnvironment = [];
    private static bool $spaceEnvironmentCaptured = false;

    private ?RepositoryInterface $environmentRepository = null;

    /** @var array<string, array{0: bool, 1: ?string}> */
    private array $originalEnvironment = [];

    private static function captureSpaceEnvironment(): void
    {
        if (self::$spaceEnvironmentCaptured) {
            return;
        }

        $repository = self::mutableEnvironmentRepository();
        foreach (['APP_NAME', 'BEAM_BRAND_NAME'] as $key) {
            self::$spaceEnvironment[$key] = [$repository->has($key), $repository->get($key)];
        }
        self::$spaceEnvironmentCaptured = true;
    }

    private static function mutableEnvironmentRepository(): RepositoryInterface
    {
        return RepositoryBuilder::createWithDefaultAdapters()->make();
    }

    private function bootWithBrandEnvironment(string $appName, ?string $brandName): void
    {
        self::captureSpaceEnvironment();
        $repository = self::mutableEnvironmentRepository();

        if ($this->environmentRepository === null) {
            $this->environmentRepository = $repository;
            foreach (['APP_NAME', 'BEAM_BRAND_NAME'] as $key) {
                $this->originalEnvironment[$key] = [$repository->has($key), $repository->get($key)];
            }
        }

        $this->writeEnvironment($repository, $appName, $brandName);

        // Re-evaluate the framework config in this app after the environment repository change.
        $this->app['config']->set('app', require config_path('app.php'));
        $this->app['config']->set('beam.brand', require config_path('beam/brand.php'));
    }

    private function writeEnvironment(RepositoryInterface $repository, string $appName, ?string $brandName): void
    {
        if (! $repository->set('APP_NAME', $appName)) {
            throw new \RuntimeException('Could not set APP_NAME through the environment repository.');
        }

        $written = $brandName === null
            ? $repository->clear('BEAM_BRAND_NAME')
            : $repository->set('BEAM_BRAND_NAME', $brandName);

        if (! $written) {
            throw new \RuntimeException('Could not set or clear BEAM_BRAND_NAME through the environment repository.');
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->environmentRepository !== null) {
                foreach ($this->originalEnvironment as $key => [$wasSet, $value]) {
                    $restored = $wasSet
                        ? $this->environmentRepository->set($key, (string) $value)
                        : $this->environmentRepository->clear($key);

                    if (! $restored) {
                        throw new \RuntimeException("Could not restore {$key} through the environment repository.");
                    }
                }
            }
        }
    }

    public function test_the_shell_uses_app_name_when_beam_brand_name_is_unset(): void
    {
        $this->bootWithBrandEnvironment('Ticket 49 Satellite Host', null);

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Ticket 49 Satellite Host', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Ticket 49 Satellite Host</title>', false);
    }

    public function test_app_name_laravel_falls_back_to_the_product_name(): void
    {
        $this->bootWithBrandEnvironment('Laravel', null);

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Satellite', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Satellite</title>', false);
        $response->assertDontSee('<title>Laravel</title>', false);
    }

    public function test_blank_beam_brand_name_uses_app_name(): void
    {
        $this->bootWithBrandEnvironment('Ticket 49 Satellite Host', '');

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Ticket 49 Satellite Host', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Ticket 49 Satellite Host</title>', false);
    }

    public function test_blank_beam_brand_name_with_laravel_falls_back_to_product_name(): void
    {
        $this->bootWithBrandEnvironment('Laravel', '');

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Satellite', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Satellite</title>', false);
        $response->assertDontSee('<title>Laravel</title>', false);
    }

    public function test_explicit_beam_brand_name_wins_over_app_name(): void
    {
        $this->bootWithBrandEnvironment('Ticket 49 Satellite Host', 'Acme Field Notes');

        $response = $this->get('/login');

        $response->assertOk();
        $this->assertSame('Acme Field Notes', $response->viewData('page')['props']['brand']['name'] ?? null);
        $response->assertSee('<title>Acme Field Notes</title>', false);
        $response->assertDontSee('<title>Ticket 49 Satellite Host</title>', false);
    }

    public function test_environment_is_restored_to_its_pre_class_snapshot(): void
    {
        self::captureSpaceEnvironment();
        $repository = Env::getRepository();

        foreach (self::$spaceEnvironment as $key => [$wasSet, $value]) {
            $this->assertSame($wasSet, $repository->has($key), "Unexpected {$key} presence after the class ran.");
            if ($wasSet) {
                $this->assertSame($value, $repository->get($key), "Unexpected {$key} value after the class ran.");
            }
        }
    }
}
