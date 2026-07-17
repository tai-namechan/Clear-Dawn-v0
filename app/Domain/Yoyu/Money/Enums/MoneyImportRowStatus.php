<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyImportRowStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case SkippedDuplicate = 'skipped_duplicate';
    case NeedsReview = 'needs_review';
    case Error = 'error';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
