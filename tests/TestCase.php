<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * The registration door this test class boots with (purchase-walkthrough BUY-05): ACCOUNT_REGISTRATION as a host's
     * .env would set it, or null to leave the environment alone. The door mounts its routes at boot, so it is set
     * before the application is created and restored after.
     */
    protected ?string $accountRegistration = null;

    /** @var array{0: string|false, 1: mixed, 2: mixed}|null */
    private ?array $accountRegistrationBefore = null;

    public function createApplication()
    {
        if ($this->accountRegistration !== null) {
            $this->accountRegistrationBefore ??= [getenv('ACCOUNT_REGISTRATION'), $_ENV['ACCOUNT_REGISTRATION'] ?? null, $_SERVER['ACCOUNT_REGISTRATION'] ?? null];
            putenv("ACCOUNT_REGISTRATION={$this->accountRegistration}");
            $_ENV['ACCOUNT_REGISTRATION'] = $_SERVER['ACCOUNT_REGISTRATION'] = $this->accountRegistration;
        }

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            $this->restoreAccountRegistration();
        }
    }

    private function restoreAccountRegistration(): void
    {
        if ($this->accountRegistrationBefore !== null) {
            [$env, $envArray, $server] = $this->accountRegistrationBefore;
            $env === false ? putenv('ACCOUNT_REGISTRATION') : putenv("ACCOUNT_REGISTRATION={$env}");
            if ($envArray === null) {
                unset($_ENV['ACCOUNT_REGISTRATION']);
            } else {
                $_ENV['ACCOUNT_REGISTRATION'] = $envArray;
            }
            if ($server === null) {
                unset($_SERVER['ACCOUNT_REGISTRATION']);
            } else {
                $_SERVER['ACCOUNT_REGISTRATION'] = $server;
            }
            $this->accountRegistrationBefore = null;
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
