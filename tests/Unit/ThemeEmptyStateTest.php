<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * C50 — the account Theme page's empty state (the owner view at /account/theme; members 403) must be a
 * customer-safe PRODUCT state, never a developer instruction. `entry` is null on a site whose central
 * theme row was never seeded; the copy shown there must not name an Artisan command or a seeder class.
 *
 * A source guard (plain PHPUnit, no app boot): the starter has no JS test harness, and the empty-state
 * copy is a one-branch literal in the Inertia page, so this asserts the forbidden developer strings are
 * absent and a customer-safe product message is present.
 */
class ThemeEmptyStateTest extends TestCase
{
    public function test_theme_empty_state_is_customer_safe_not_a_seeder_command(): void
    {
        $page = file_get_contents(__DIR__.'/../../resources/js/pages/account/theme.tsx');

        self::assertNotFalse($page);
        self::assertStringNotContainsString('php artisan', $page, 'the Theme empty state must not instruct a customer to run an Artisan command');
        self::assertStringNotContainsString('db:seed', $page, 'the Theme empty state must not expose a seeder invocation');
        self::assertStringContainsString('default theme', $page, 'the Theme empty state should be a customer-safe product message');
    }
}
