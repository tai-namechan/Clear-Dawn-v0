<?php

namespace App\Enums;

/**
 * 栄養値の基準種別（1サービング / 100g / 1包装）。
 */
enum NutritionBasis: string
{
    case Serving = 'serving';
    case Per100g = '100g';
    case Package = 'package';

    public function label(): string
    {
        return match ($this) {
            self::Serving => '1サービング',
            self::Per100g => '100g あたり',
            self::Package => '1包装あたり',
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
