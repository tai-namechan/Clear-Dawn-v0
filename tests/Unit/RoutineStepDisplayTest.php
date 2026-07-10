<?php

namespace Tests\Unit;

use App\Models\RoutineItem;
use App\Models\RoutinePlanStep;
use App\Models\RoutineStep;
use App\Support\RoutineStepDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutineStepDisplayTest extends TestCase
{
    /**
     * 表示名は title 優先、未入力なら実施項目名。
     *
     * @param  array{title: ?string, itemName: ?string, expected: string}  $case
     */
    #[DataProvider('displayNameProvider')]
    public function test_resolve_name_prefers_title_then_item_name(array $case): void
    {
        $item = $case['itemName'] !== null
            ? new RoutineItem(['name' => $case['itemName']])
            : null;

        $this->assertSame(
            $case['expected'],
            RoutineStepDisplay::resolveName($case['title'], $item),
        );
    }

    /**
     * @return array<string, array{0: array{title: ?string, itemName: ?string, expected: string}}>
     */
    public static function displayNameProvider(): array
    {
        return [
            'title wins' => [[
                'title' => '股関節を開く準備',
                'itemName' => 'WGS',
                'expected' => '股関節を開く準備',
            ]],
            'fallback to item' => [[
                'title' => null,
                'itemName' => 'カノン Aパート',
                'expected' => 'カノン Aパート',
            ]],
            'blank title falls back' => [[
                'title' => '   ',
                'itemName' => 'AWS IAM章',
                'expected' => 'AWS IAM章',
            ]],
            'both empty' => [[
                'title' => null,
                'itemName' => null,
                'expected' => '',
            ]],
        ];
    }

    public function test_resolve_video_id_prefers_step_then_item_default(): void
    {
        $item = new RoutineItem(['default_video_id' => '01ITEMDEFAULTVIDEO000000000']);

        $this->assertSame(
            '01STEPVIDEO000000000000000',
            RoutineStepDisplay::resolveVideoId('01STEPVIDEO000000000000000', $item),
        );

        $this->assertSame(
            '01ITEMDEFAULTVIDEO000000000',
            RoutineStepDisplay::resolveVideoId(null, $item),
        );

        $this->assertNull(RoutineStepDisplay::resolveVideoId(null, new RoutineItem));
    }

    public function test_from_routine_step_uses_loaded_relations(): void
    {
        $item = new RoutineItem([
            'name' => 'スクワット',
            'default_video_id' => '01DEFAULTSQUATVIDEO000000',
        ]);
        $step = new RoutineStep([
            'title' => '投手用フォーム確認',
            'video_id' => null,
        ]);
        $step->setRelation('routineItem', $item);

        $resolved = RoutineStepDisplay::fromRoutineStep($step);

        $this->assertSame('投手用フォーム確認', $resolved['display_name']);
        $this->assertSame('01DEFAULTSQUATVIDEO000000', $resolved['video_id']);
    }

    public function test_from_plan_step_uses_loaded_relations(): void
    {
        $item = new RoutineItem([
            'name' => 'スクワット',
            'default_video_id' => null,
        ]);
        $step = new RoutinePlanStep([
            'title' => null,
            'video_id' => '01PLANSTEPVIDEO0000000000',
        ]);
        $step->setRelation('routineItem', $item);

        $resolved = RoutineStepDisplay::fromPlanStep($step);

        $this->assertSame('スクワット', $resolved['display_name']);
        $this->assertSame('01PLANSTEPVIDEO0000000000', $resolved['video_id']);
    }
}
