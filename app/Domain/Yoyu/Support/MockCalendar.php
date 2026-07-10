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
                'title' => 'ジム：筋トレ',
                'start' => $base->copy()->setTime(13, 0)->toIso8601String(),
                'end' => $base->copy()->setTime(14, 15)->toIso8601String(),
                'place' => 'ジム',
                'travel_min' => 20,
                'color' => '#43A860',
            ],
            [
                'id' => 'e3',
                'title' => 'ヨガ',
                'start' => $base->copy()->setTime(18, 30)->toIso8601String(),
                'end' => $base->copy()->setTime(19, 45)->toIso8601String(),
                'place' => 'スタジオ',
                'travel_min' => 25,
                'color' => '#8A6FC9',
            ],
        ];
    }

    /**
     * @return array{goal: string, action: string, estimate: int}
     */
    public static function clearDawnHand(): array
    {
        return [
            'goal' => '今月中にポートフォリオを整える',
            'action' => 'READMEを30分修正する',
            'estimate' => 30,
        ];
    }
}
