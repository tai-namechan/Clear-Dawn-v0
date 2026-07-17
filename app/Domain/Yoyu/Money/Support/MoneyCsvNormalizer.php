<?php

namespace App\Domain\Yoyu\Money\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pure helpers for CSV row date/amount normalization (no I/O).
 */
final class MoneyCsvNormalizer
{
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

        return CarbonImmutable::parse($raw)->toDateString();
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
