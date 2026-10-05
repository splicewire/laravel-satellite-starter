<?php

declare(strict_types=1);

namespace App\Data\Pages;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where this host mounted the four platform-connection operations.
 *
 * ⚠️ Read from the ROUTE TABLE by name, not written as literals. `Particle::ops()` composes each URL
 * from the declared subject's coordinates — `ActorSubject` contributes none, so these land at
 * `{prefix}/platform-connection/{op}` — and the prefix is a host choice. A literal here would be a
 * second authority on a URL the router already owns, and it would keep answering plausibly after the
 * mount moved, which is the failure mode that produces a screen whose every button 404s in
 * production and passes every test that never opened it.
 *
 * The names are the primary mount's (`{resourceKey}.{op}`), not the deprecated `.op.` alias's.
 */
#[TypeScript]
final class PlatformConnectionEndpointsData extends Data
{
    public function __construct(
        public string $connect,
        public string $poll,
        public string $check,
        public string $disconnect,
    ) {}

    public static function fromRoutes(): self
    {
        return new self(
            connect: route('platform-connection.connect', absolute: false),
            poll: route('platform-connection.poll', absolute: false),
            check: route('platform-connection.check', absolute: false),
            disconnect: route('platform-connection.disconnect', absolute: false),
        );
    }

    /** Whether every operation this screen drives is actually mounted at this host. */
    public static function mounted(): bool
    {
        foreach (['connect', 'poll', 'check', 'disconnect'] as $op) {
            if (! Route::has("platform-connection.{$op}")) {
                return false;
            }
        }

        return true;
    }
}
