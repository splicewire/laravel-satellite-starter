<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `config/splicewire/satellite.php` is install output — `splicewire:satellite:install` publishes it
 * under the `splicewire-satellite-config` tag during `composer setup` — and this starter tracks it
 * pristine, the way it already tracks every other published config.
 *
 * ⚠️ The hazard that makes this test necessary is not hypothetical and is IN-ESTATE. The reference
 * consumer ~/Herd/splicewire-app tracks its copy of this same config, and that copy silently kept an
 * `(int)` cast on `engine_key.id` which the package had deliberately REMOVED — the cast breaks
 * uuid-keyed `personal_access_tokens`. It was committed once and never refreshed, and nothing said a
 * word. Tracking a published config buys that failure mode unless something watches it.
 *
 * So: regeneration is a command (`vendor:publish --tag=splicewire-satellite-config --force`),
 * verification is this test, and this test never writes.
 *
 * If a deliberate host customization is ever wanted here, this test is the right place to record it —
 * replace the byte-equality with an explicit diff of the intended deviation, so the customization is
 * declared rather than indistinguishable from staleness.
 */
class PublishedConfigDriftTest extends TestCase
{
    public function test_the_published_satellite_config_still_matches_the_package_it_came_from(): void
    {
        $tracked = config_path('splicewire/satellite.php');
        $source = base_path('vendor/splicewire/laravel-satellite/config/splicewire/satellite.php');

        $this->assertFileExists($tracked, 'run `php artisan vendor:publish --tag=splicewire-satellite-config`');

        // Guard the instrument itself: if the package moved its config, byte-equality against a
        // missing file would be unmeasurable, and an absent source must not read as "no drift".
        $this->assertFileExists(
            $source,
            'the package config is not where this test expects it — the comparison did not run, '
                .'which is NOT the same as no drift'
        );

        $this->assertSame(
            file_get_contents($source),
            file_get_contents($tracked),
            'config/splicewire/satellite.php has drifted from the package it was published from. '
                .'If the package changed, re-publish: '
                .'`php artisan vendor:publish --tag=splicewire-satellite-config --force`. '
                .'If this host means to customize it, declare that here instead of leaving it silent.'
        );
    }
}
