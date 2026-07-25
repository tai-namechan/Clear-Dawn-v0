<?php

namespace App\Enums;

/**
 * 食品情報の確認状態（出典とユーザー確認の区別）。
 */
enum FoodConfirmationStatus: string
{
    case OpenFoodFacts = 'openfoodfacts';
    case LabelOcr = 'label_ocr';
    case PhotoEstimate = 'photo_estimate';
    case MenuEstimate = 'menu_estimate';
    case UserConfirmed = 'user_confirmed';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::OpenFoodFacts => '外部DB（Open Food Facts）',
            self::LabelOcr => '成分表AI読み取り',
            self::PhotoEstimate => '料理写真AI推定',
            self::MenuEstimate => '外食メニュー推定',
            self::UserConfirmed => 'ユーザー確認済み',
            self::Manual => '手入力',
        };
    }

    /**
     * lookup.source から確認状態へマップする。
     */
    public static function fromLookupSource(?string $source): self
    {
        return match ($source) {
            'openfoodfacts' => self::OpenFoodFacts,
            'label_ocr' => self::LabelOcr,
            'ai_photo_estimate' => self::PhotoEstimate,
            'ai_menu_estimate', 'chain_scrape' => self::MenuEstimate,
            default => self::UserConfirmed,
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
