<?php

namespace App\Enums;

/**
 * Story画像の種類。
 */
enum BodyStoryKind: string
{
    case Nutrition = 'nutrition';
    case Weight = 'weight';
    case Weekly = 'weekly';
    case Body = 'body';

    /**
     * 固定テンプレートの公開URL。
     */
    public function templateUrl(): string
    {
        return match ($this) {
            self::Nutrition => '/images/products/today-food-log.png',
            self::Weight => '/images/products/today-weight-log.png',
            self::Weekly => '/images/products/weekly-log.png',
            self::Body => '/images/products/body-story.png',
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
