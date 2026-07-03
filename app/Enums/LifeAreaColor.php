<?php

namespace App\Enums;

/**
 * Life Area のカラーパレットキー。
 * 実色は resources/css/app.css の Clear Dawn トークンにマッピングする
 * （自由 hex 入力は不可: docs/product/screens/life-areas.md）。
 */
enum LifeAreaColor: string
{
    case Dawn = 'dawn';
    case Sunrise = 'sunrise';
    case Gilt = 'gilt';
    case Moss = 'moss';
    case Mist = 'mist';
    case Lavender = 'lavender';
}
