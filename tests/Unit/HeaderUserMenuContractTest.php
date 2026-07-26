<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove header user control collapses to an avatar on phone widths.
 */
class HeaderUserMenuContractTest extends TestCase
{
    public function test_mobile_header_shows_avatar_without_username(): void
    {
        $source = $this->componentSource(
            'resources/js/components/HeaderUserMenu.vue',
        );

        $this->assertStringContainsString(
            'hidden max-w-[10rem] truncate font-serif tracking-[0.06em] text-cd-ink md:inline',
            $source,
            'Username text must hide below md so the header stays compact on phones',
        );
        $this->assertStringContainsString(
            'hidden size-4 shrink-0 text-cd-ink-muted opacity-70 transition-opacity group-hover:opacity-100 md:block',
            $source,
            'Chevron beside the username must also hide on mobile',
        );
        $this->assertStringContainsString(
            ':aria-label="`${user.name} のメニュー`"',
            $source,
            'Avatar-only control still needs an accessible name',
        );
        $this->assertStringContainsString(
            'getInitials(user.name)',
            $source,
            'Fallback avatar initials remain available when no photo is set',
        );
    }

    private function componentSource(string $relativePath): string
    {
        $absolute = base_path($relativePath);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
