<?php

namespace App\Enums;

/**
 * 体組成測定の入力経路。
 */
enum BodyMeasurementSource: string
{
    case Pdf = 'pdf';
    case Manual = 'manual';

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
