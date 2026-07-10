<?php

namespace App\Domain\Yoyu\Support;

use Carbon\Carbon;

/**
 * MVP mock calendar / Clear Dawn hand until real integrations land.
 */
final class MockCalendar
{
    /**
     * @return list<array{id: string, title: string, start: string, end: string, place: string, travel_min: int, color: string}>
     */
    public static function todayEvents(): array
    {
        $base = Carbon::today();

        return [
            [
                'id' => 'e1',
                'title' => '仕事：オンラインMTG',
                'start' => $base->copy()->setTime(10, 0)->toIso8601String(),
                'end' => $base->copy()->setTime(11, 0)->toIso8601String(),
                'place' => '自宅',
                'travel_min' => 0,
                'color' => '#4A7DC4',
            ],
            [
                'id' => 'e2',
                'title' => 'ジム：筋トレ（脚の日）',
                'start' => $base->copy()->setTime(13, 0)->toIso8601String(),
                'end' => $base->copy()->setTime(14, 15)->toIso8601String(),
                'place' => 'ジム',
                'travel_min' => 20,
                'color' => '#43A860',
            ],
            [
                'id' => 'e3',
                'title' => 'ヨガ：リラックス系 75分',
                'start' => $base->copy()->setTime(18, 30)->toIso8601String(),
                'end' => $base->copy()->setTime(19, 45)->toIso8601String(),
                'place' => 'スタジオ',
                'travel_min' => 25,
                'color' => '#8A6FC9',
            ],
            [
                'id' => 'e4',
                'title' => '買い出し',
                'start' => $base->copy()->setTime(20, 15)->toIso8601String(),
                'end' => $base->copy()->setTime(20, 45)->toIso8601String(),
                'place' => 'スーパー',
                'travel_min' => 10,
                'color' => '#DF9A2E',
            ],
        ];
    }

    /**
     * @return array{goal: string, action: string, estimate: int}
     */
    public static function clearDawnHand(): array
    {
        return [
            'goal' => '7月中に転職ポートフォリオ完成',
            'action' => 'READMEを30分修正する',
            'estimate' => 30,
        ];
    }
}
