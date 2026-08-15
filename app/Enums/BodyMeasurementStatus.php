<?php

namespace App\Enums;

/**
 * 体組成測定の解析・確定状態。
 */
enum BodyMeasurementStatus: string
{
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    /**
     * 保存用の値一覧。
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
