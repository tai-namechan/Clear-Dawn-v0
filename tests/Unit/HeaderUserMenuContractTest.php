<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove header user control collapses to a person icon on phone widths.
 */
class HeaderUserMenuContractTest extends TestCase
{
    public function test_mobile_header_shows_person_icon_without_username(): void
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
            'class="shrink-0 md:hidden"',
            $source,
            'Mobile trigger uses the person icon alone inside the header control pill',
        );
        $this->assertStringContainsString(
            ':stroke-width="2.2"',
            $source,
            'Person icon stroke must stay heavy enough on the translucent header control',
        );
        $this->assertStringNotContainsString(
            'bg-muted text-cd-ink md:hidden',
            $source,
            'Nested white chip made the light icon unreadable on the mobile header control',
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
