<?php

namespace Tests\Fixtures\HostContext;

use Illuminate\Support\ServiceProvider;
use Schemastud\Frame\Registry\ContextGrain;
use Schemastud\Frame\Registry\WidgetContext;
use Schemastud\Frame\Registry\WidgetContextRegistry;
use Splicewire\Beam\Particle\ParticleResourceRegistry;

/**
 * A host's own render context, in tests only (frame-foundation 92, story 50). Nothing in app/ changes.
 *
 * It registers a record-grain context `card` that falls back to `list-item`, as this host, from boot() (the
 * registry's own rule: never register()), and two resources that declare in it. This provider is registered by the
 * tests that use it, never by the app.
 */
class HostContextProvider extends ServiceProvider
{
    public const CONTEXT = 'card';

    public const REGISTRANT = 'laravel-beam-starter/tests';

    public function boot(): void
    {
        app(WidgetContextRegistry::class)->register(
            new WidgetContext(self::CONTEXT, ContextGrain::Record, fallback: ['list-item']),
            by: self::REGISTRANT,
        );

        $resources = app(ParticleResourceRegistry::class);
        $resources->registerClass(CardBoundData::class);
        $resources->registerClass(CardFallbackData::class);
    }
}
