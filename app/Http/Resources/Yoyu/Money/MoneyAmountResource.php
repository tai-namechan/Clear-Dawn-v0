<?php

namespace App\Http\Resources\Yoyu\Money;

use App\Domain\Yoyu\Money\Support\MoneyAmount;

/**
 * Formats money amounts for Inertia / HTTP as string minor units.
 */
final class MoneyAmountResource
{
    /**
     * @return array{amountMinor: string, currency: string}
     */
    public static function format(string|int $amountMinor, string $currency = 'JPY'): array
    {
        return [
            'amountMinor' => MoneyAmount::ofMinor($amountMinor)->toString(),
            'currency' => $currency,
        ];
    }

    public static function toInt(string|int $amountMinor, bool $nonNegative = true): int
    {
        $amount = MoneyAmount::ofMinor($amountMinor);

        if ($nonNegative) {
            $amount->assertNonNegative();
        }

        return $amount->toInt();
    }

    /**
     * Cast validated string minor fields to int in-place.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $nonNegativeKeys
     * @param  list<string>  $signedKeys
     * @return array<string, mixed>
     */
    public static function castMinors(
        array $data,
        array $nonNegativeKeys = [],
        array $signedKeys = [],
    ): array {
        foreach ($nonNegativeKeys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = self::toInt($data[$key], true);
            }
        }

        foreach ($signedKeys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = self::toInt($data[$key], false);
            }
        }

        return $data;
    }
}
