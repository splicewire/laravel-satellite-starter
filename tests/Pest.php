<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Browser suites mounted from installed capability packages
|--------------------------------------------------------------------------
|
| ticket 13 (browser-journeys): a Pest 4 host discovers and runs the browser
| suites shipped by the installed splicewire/* packages through one static
| glob. The host binds its own TestCase plus Pest's Browsable mixin here --
| the plugin's auto-binding is rooted at the project root and misses the
| vendored realpaths -- so `pest --testsuite=Browser` boots the app and drives
| the browser for every matched package suite. The starter authors no browser
| tests of its own; it is the family's maintained reference consumer.
|
*/

pest()->extend(TestCase::class)
    ->use(Pest\Browser\Browsable::class)
    ->in('../vendor/splicewire/*/tests/Browser');
