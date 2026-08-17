<?php

namespace App\Enums;

/**
 * 日次メトリクスの入力経路。
 */
enum MetricRecordSource: string
{
    case Manual = 'manual';
    case BodyPdf = 'body_pdf';

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
