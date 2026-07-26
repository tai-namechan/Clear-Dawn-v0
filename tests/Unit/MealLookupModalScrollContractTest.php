<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove tall barcode / restaurant confirm dialogs stay scrollable on phones.
 */
class MealLookupModalScrollContractTest extends TestCase
{
    public function test_barcode_lookup_modal_limits_height_and_scrolls(): void
    {
        $source = $this->componentSource(
            'resources/js/components/BarcodeLookupModal.vue',
        );

        $this->assertStringContainsString(
            'max-h-[90dvh] overflow-y-auto bg-cd-surface sm:max-w-lg',
            $source,
            'Confirm form must scroll inside the viewport instead of overflowing',
        );
        $this->assertStringContainsString(
            '保存して食事に追加',
            $source,
            'Confirm primary action must remain present',
        );
    }

    public function test_restaurant_lookup_modal_limits_height_and_scrolls(): void
    {
        $source = $this->componentSource(
            'resources/js/components/RestaurantLookupModal.vue',
        );

        $this->assertStringContainsString(
            'max-h-[90dvh] overflow-y-auto bg-cd-surface sm:max-w-lg',
            $source,
            'Restaurant confirm dialog must also scroll on short viewports',
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
