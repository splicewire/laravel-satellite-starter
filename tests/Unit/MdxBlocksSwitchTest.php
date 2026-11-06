<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * EP-42 (starter room, 10-27): where MDX pages hold blocks, their profile offers ApiReference (laravel-beam-docs) and
 * SiteNav and ManifestTable (laravel-beam-ux), which the packages' shipped docs pages place, and the editor refused those
 * pages without them. beam-inertia registers the last two where the host says its MDX pages hold blocks; ApiReference
 * is the starter's. The switch is one env value the Vite build mirrors, and its note says to re-seed after turning it on.
 */
class MdxBlocksSwitchTest extends TestCase
{
    public function test_mdx_pages_holding_blocks_register_what_the_shipped_docs_pages_place(): void
    {
        $root = dirname(__DIR__, 2);
        $app = file_get_contents($root.'/resources/js/app.tsx');
        $env = file_get_contents($root.'/.env.example');

        $this->assertStringContainsString($this->squashWhitespace("mdxHoldsBlocks: import.meta.env.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true'"), $this->squashWhitespace($app));
        $this->assertMatchesRegularExpression("/components: import\\.meta\\.env\\.VITE_BEAM_UX_MDX_HOLDS_BLOCKS === 'true' \\? \\{ ApiReference \\}/", $this->squashWhitespace($app));
        $this->assertStringContainsString('BEAM_UX_MDX_HOLDS_BLOCKS=false', $env);
        $this->assertStringContainsString('VITE_BEAM_UX_MDX_HOLDS_BLOCKS="${BEAM_UX_MDX_HOLDS_BLOCKS}"', $env);
        $this->assertStringContainsString('splicewire:beam:seed', $env);
    }

    /**
     * Collapse runs of whitespace to a single space so a prettier line-wrap of the long
     * components:/mdxHoldsBlocks MDX-flag config reads the same as its single-line form. beam-dx
     * 84504222 approach, applied ONLY to the two formatting-sensitive app.tsx MDX-config assertions
     * above (the second is a regex whose literal single spaces match the collapsed form). It collapses
     * whitespace, never tokens, so a changed condition/value/component still fails the assertion.
     */
    private function squashWhitespace(string $value): string
    {
        return (string) preg_replace('/\s+/', ' ', $value);
    }
}
