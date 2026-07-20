<?php

namespace App\Domain\Yoyu\Money\Support;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

/**
 * Pure helpers for CSV row date/amount normalization (no I/O).
 */
final class MoneyCsvNormalizer
{
    /**
     * date_format 未指定時に許容するフォーマット。曖昧な暗黙解釈
     * （例: 01/02/03 の月日順推測）を避けるため、明示リスト以外は invalid_date に落とす。
     *
     * @var list<string>
     */
    private const FALLBACK_DATE_FORMATS = [
        'Y-m-d',
        'Y-n-j',
        'Y/m/d',
        'Y/n/j',
        'Y.m.d',
        'Y.n.j',
        'Ymd',
        'Y年n月j日',
    ];

    /**
     * @param  array<string, mixed>  $mapping
     */
    public function parseDate(string $raw, array $mapping): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('Empty date value.');
        }

        $format = isset($mapping['date_format']) && is_string($mapping['date_format']) && $mapping['date_format'] !== ''
            ? $mapping['date_format']
            : null;

        if ($format !== null) {
            $parsed = CarbonImmutable::createFromFormat($format, $raw);
            if ($parsed === false) {
                throw new InvalidArgumentException("Date '{$raw}' does not match format {$format}.");
            }

            return $parsed->toDateString();
        }

        foreach (self::FALLBACK_DATE_FORMATS as $candidate) {
            try {
                $parsed = CarbonImmutable::createFromFormat($candidate, $raw);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($parsed !== false && $parsed->format($candidate) === $raw) {
                return $parsed->toDateString();
            }
        }

        throw new InvalidArgumentException("Date '{$raw}' does not match any supported format.");
    }

    /**
     * Resolve signed amount in minor units and direction from mapping + row cells.
     *
     * @param  array<int|string, string|null>  $cells  keyed by column name or index
     * @param  array<string, mixed>  $mapping
     * @return array{amount_minor: int, direction: string}
     */
    public function resolveAmount(array $cells, array $mapping): array
    {
        $amountSign = (string) ($mapping['amount_sign'] ?? 'expense_positive');

        if (isset($mapping['amount_column'])) {
            $raw = $this->cell($cells, $mapping['amount_column']);
            $signed = $this->parseAmountToMinor($raw);
            $abs = abs($signed);

            if ($amountSign === 'income_positive') {
                $direction = $signed >= 0 ? 'inflow' : 'outflow';
            } else {
                // expense_positive: positive → outflow, negative → inflow (refund)
                $direction = $signed >= 0 ? 'outflow' : 'inflow';
            }

            return [
                'amount_minor' => $abs,
                'direction' => $direction,
            ];
        }

        $debitCol = $mapping['debit_column'] ?? null;
        $creditCol = $mapping['credit_column'] ?? null;
        if ($debitCol === null && $creditCol === null) {
            throw new InvalidArgumentException('mapping_config requires amount_column or debit/credit columns.');
        }

        $debitRaw = $debitCol !== null ? trim($this->cell($cells, $debitCol)) : '';
        $creditRaw = $creditCol !== null ? trim($this->cell($cells, $creditCol)) : '';

        $debit = $debitRaw !== '' ? abs($this->parseAmountToMinor($debitRaw)) : 0;
        $credit = $creditRaw !== '' ? abs($this->parseAmountToMinor($creditRaw)) : 0;

        if ($debit > 0 && $credit > 0) {
            throw new InvalidArgumentException('Both debit and credit are populated.');
        }

        if ($debit > 0) {
            return ['amount_minor' => $debit, 'direction' => 'outflow'];
        }

        if ($credit > 0) {
            return ['amount_minor' => $credit, 'direction' => 'inflow'];
        }

        throw new InvalidArgumentException('Neither debit nor credit has a value.');
    }

    public function parseAmountToMinor(string $raw): int
    {
        $normalized = trim($raw);
        $normalized = str_replace([',', '¥', '￥', ' '], '', $normalized);
        $normalized = str_replace(['△', '▲'], '-', $normalized);

        if ($normalized === '' || preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid amount: {$raw}");
        }

        if (str_contains($normalized, '.')) {
            // JPY MVP: drop fractional yen rather than invent rounding policy.
            $normalized = explode('.', $normalized, 2)[0];
        }

        return (int) $normalized;
    }

    public function normalizeDescription(string $raw): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($raw)) ?? trim($raw);

        return mb_strtolower($collapsed);
    }

    /**
     * Stable row hash for strong duplicate detection.
     */
    public function rowHash(string $accountId, string $occurredOn, int $amountMinor, string $descriptionNormalized): string
    {
        return hash('sha256', implode('|', [
            $accountId,
            $occurredOn,
            (string) $amountMinor,
            $descriptionNormalized,
        ]));
    }

    /**
     * @param  array<int|string, string|null>  $cells
     */
    private function cell(array $cells, int|string $key): string
    {
        $value = $cells[$key] ?? null;

        return is_string($value) ? $value : (string) ($value ?? '');
    }
}
