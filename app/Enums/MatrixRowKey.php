<?php

namespace App\Enums;

enum MatrixRowKey: string
{
    case Monthly = 'monthly';
    case Current = 'current';
    case Future = 'future';

    /**
     * 表示ラベル（matrix_rows.label の正）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => '1ヶ月くらいの間でやるべきこと',
            self::Current => '今やるべきこと',
            self::Future => '将来どうなっていたいか',
        };
    }

    /**
     * 行順（matrix_rows.sort_order の正）。
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Current => 2,
            self::Future => 3,
        };
    }

    /**
     * 完了チェックボックスを表示できる行か（Phase 1 は current のみ true）。
     */
    public function isCheckable(): bool
    {
        return $this === self::Current;
    }
}
