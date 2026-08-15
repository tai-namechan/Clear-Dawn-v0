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
            self::Nutrition => '/images/products/today-food-log.png',
            self::Weight => '/images/products/today-weight-log.png',
            self::Weekly => '/images/products/weekly-log.png',
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
