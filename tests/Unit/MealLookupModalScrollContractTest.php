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

    public function test_scan_step_shows_label_ocr_as_visible_action_card(): void
    {
        $source = $this->componentSource(
            'resources/js/components/BarcodeLookupModal.vue',
        );

        $this->assertStringContainsString(
            'startOcrWithoutBarcode',
            $source,
            'Scan step must keep a direct entry to label OCR without a barcode miss',
        );
        $this->assertStringContainsString(
            '成分表を撮影',
            $source,
            'Label OCR must use a clear action title, not muted helper text alone',
        );
        $this->assertStringContainsString(
            'バーコードがない商品も、栄養成分表示から登録できます',
            $source,
            'OCR card must explain when to use label capture',
        );
        $this->assertStringNotContainsString(
            'バーコードがない商品は、成分表の撮影から登録できます',
            $source,
            'Muted text-only OCR link is too easy to miss on mobile',
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
