<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove session exercise video shell supports portrait/landscape viewing.
 */
class SessionExerciseVideoContractTest extends TestCase
{
    public function test_session_show_uses_orientation_aware_video_shell(): void
    {
        $show = $this->pageSource('resources/js/pages/Sessions/Show.vue');
        $video = $this->pageSource(
            'resources/js/components/routine/SessionExerciseVideo.vue',
        );
        $css = $this->pageSource('resources/css/app.css');

        $this->assertStringContainsString(
            'SessionExerciseVideo',
            $show,
            'Session show must render the orientation-aware video shell',
        );
        $this->assertStringNotContainsString(
            'aspect-video max-h-[min(42vh,22rem)]',
            $show,
            'Fixed landscape aspect-video clamp must be removed from session show',
        );
        $this->assertStringContainsString(
            'loadedmetadata',
            $video,
            'Video shell must detect orientation from metadata',
        );
        $this->assertStringContainsString(
            'is-portrait',
            $video,
            'Portrait class must be applied for vertical clips',
        );
        $this->assertStringContainsString(
            'is-landscape',
            $video,
            'Landscape class must be applied for horizontal clips',
        );
        $this->assertStringContainsString(
            'object-fit: contain',
            $css,
            'Exercise video must use contain so form is not cropped',
        );
        $this->assertStringContainsString(
            '.exercise-video-shell.is-portrait',
            $css,
            'Portrait shell height rules must exist',
        );
        $this->assertStringContainsString(
            'xl:grid-cols-[minmax(0,1.35fr)_minmax(15rem,0.85fr)]',
            $show,
            'Set logging must sit beside the video on wide layouts',
        );
        $this->assertStringContainsString(
            'SessionBlockLogger',
            $show,
            'Session show must render the compact set logger beside the video',
        );
        $this->assertStringNotContainsString(
            'hidden border-t border-cd-line/40 bg-white/70 px-3 py-3 lg:block',
            $show,
            'Set logging must not live inside the step list sidebar',
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
