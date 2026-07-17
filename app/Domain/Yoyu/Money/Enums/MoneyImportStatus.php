<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyImportStatus: string
{
    case Uploaded = 'uploaded';
    case Mapped = 'mapped';
    case Previewed = 'previewed';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
