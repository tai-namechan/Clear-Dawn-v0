<?php

namespace App\Enums;

enum BodyStoryKind: string
{
    case Nutrition = 'nutrition';
    case Weight = 'weight';
    case Weekly = 'weekly';

    public function templateUrl(): string
    {
        return match ($this) {
            self::Nutrition => '/images/products/stories/daily-nutrition.svg',
            self::Weight => '/images/products/stories/daily-weight.svg',
            self::Weekly => '/images/products/stories/weekly-summary.svg',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
