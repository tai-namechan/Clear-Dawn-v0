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
            'inline-flex items-center justify-center rounded-lg bg-muted text-cd-ink md:hidden',
            $source,
            'Mobile trigger uses the shared person icon instead of initials',
        );
        $this->assertStringContainsString(
            '<User :size="compact ? 16 : 18" :stroke-width="1.6" />',
            $source,
            'Lucide User silhouette is the mobile account affordance',
        );
        $this->assertStringContainsString(
            ':aria-label="`${user.name} のメニュー`"',
            $source,
            'Icon-only control still needs an accessible name',
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
