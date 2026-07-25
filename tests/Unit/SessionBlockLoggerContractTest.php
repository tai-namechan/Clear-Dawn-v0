<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove session set logger exposes an add-set control for extra blocks.
 */
class SessionBlockLoggerContractTest extends TestCase
{
    public function test_session_block_logger_exposes_add_set_control(): void
    {
        $logger = $this->pageSource(
            'resources/js/components/routine/SessionBlockLogger.vue',
        );
        $show = $this->pageSource('resources/js/pages/Sessions/Show.vue');

        $this->assertStringContainsString(
            'セット追加',
            $logger,
            'Set logger must expose an add-set button label',
        );
        $this->assertStringContainsString(
            'function addSet',
            $logger,
            'Set logger must implement addSet()',
        );
        $this->assertStringContainsString(
            'extraBlocks',
            $logger,
            'Extra sets beyond target_blocks must be tracked locally',
        );
        $this->assertStringContainsString(
            ':key="currentStep.id"',
            $show,
            'Session show must remount the logger per step so extra sets reset',
        );
    }

    private function pageSource(string $relative): string
    {
        $absolute = base_path($relative);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
