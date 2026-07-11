<?php

namespace App\Domain\Shared\AI;

use InvalidArgumentException;

/**
 * Fixed-scale USD money (6 fractional digits) stored as integer micros.
 * 1 micro = $0.000001. Avoids float arithmetic and comparisons.
 */
final class AiMoney
{
    public const SCALE = 6;

    public const LOG_SCALE = 4;

    private const MICRO_FACTOR = 1_000_000;

    private function __construct(private readonly int $micros) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function of(string|int $amount): self
    {
        if (is_int($amount)) {
            return new self($amount * self::MICRO_FACTOR);
        }

        $normalized = trim($amount);
        if ($normalized === '' || preg_match('/^-?\d+(\.\d+)?$/', $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid decimal amount [{$normalized}].");
        }

        return new self(self::decimalStringToMicros($normalized, ceil: false));
    }

    /**
     * Convert a DB aggregate (SUM) value into AiMoney without float comparisons.
     */
    public static function fromAggregate(mixed $value): self
    {
        if ($value === null) {
            return self::zero();
        }

        if (is_int($value)) {
            return self::of($value);
        }

        if (is_string($value) && is_numeric($value)) {
            return self::of($value);
        }

        if (is_float($value)) {
            return self::of(sprintf('%.6F', $value));
        }

        return self::zero();
    }

    public function add(self $other): self
    {
        return new self($this->micros + $other->micros);
    }

    public function sub(self $other): self
    {
        return new self($this->micros - $other->micros);
    }

    public function compare(self $other): int
    {
        return $this->micros <=> $other->micros;
    }

    public function greaterThan(self $other): bool
    {
        return $this->micros > $other->micros;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->micros >= $other->micros;
    }

    public function isNegative(): bool
    {
        return $this->micros < 0;
    }

    public function isZero(): bool
    {
        return $this->micros === 0;
    }

    /**
     * Build a conservative estimate: ceil(bytes/1e6 * inputRate + maxTokens/1e6 * outputRate).
     */
    public static function estimateFromTokensAndRates(int $inputUnits, int $outputUnits, string $inputRate, string $outputRate): self
    {
        $inputMicros = self::mulDivCeil($inputUnits, $inputRate, 1_000_000);
        $outputMicros = self::mulDivCeil($outputUnits, $outputRate, 1_000_000);

        return new self($inputMicros + $outputMicros);
    }

    public function toLogAmount(): string
    {
        // Ceil to 4 decimal places for ai_usage_logs.estimated_cost_usd.
        $logMicros = intdiv($this->micros + 99, 100);

        return self::microsToDecimalString($logMicros * 100, self::LOG_SCALE);
    }

    public function toString(int $scale = self::SCALE): string
    {
        if ($scale === self::LOG_SCALE) {
            return $this->toLogAmount();
        }

        return self::microsToDecimalString($this->micros, self::SCALE);
    }

    /**
     * Truncating used/limit ratio at SCALE fractional digits (e.g. 0.799999).
     */
    public function ratioOf(self $limit): string
    {
        if ($limit->micros <= 0) {
            return '0.000000';
        }

        $ratioMicros = intdiv($this->micros * self::MICRO_FACTOR, $limit->micros);

        return self::microsToDecimalString($ratioMicros, self::SCALE);
    }

    /**
     * True when used/limit >= threshold (e.g. "0.80") using integer arithmetic only.
     */
    public function meetsOrExceedsRatio(self $limit, string $threshold): bool
    {
        if ($limit->micros <= 0) {
            return false;
        }

        $thresholdMicros = self::decimalStringToMicros($threshold, ceil: false);

        return ($this->micros * self::MICRO_FACTOR) >= ($limit->micros * $thresholdMicros);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function decimalStringToMicros(string $amount, bool $ceil): int
    {
        $negative = str_starts_with($amount, '-');
        if ($negative) {
            $amount = substr($amount, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = substr(str_pad($fraction, self::SCALE + 1, '0'), 0, self::SCALE + 1);
        $main = substr($fraction, 0, self::SCALE);
        $remainder = (int) substr($fraction, self::SCALE, 1);

        $micros = ((int) $whole) * self::MICRO_FACTOR + (int) $main;
        if ($ceil && $remainder > 0) {
            $micros++;
        }

        return $negative ? -$micros : $micros;
    }

    /**
     * ceil(units * rate / divisor) in micros.
     */
    private static function mulDivCeil(int $units, string $rate, int $divisor): int
    {
        if ($units < 0) {
            throw new InvalidArgumentException('units must be non-negative.');
        }

        $rateMicros = self::decimalStringToMicros($rate, ceil: false);
        // units * rateMicros / divisor, ceiling.
        $numerator = $units * $rateMicros;
        if ($numerator === 0) {
            return 0;
        }

        return intdiv($numerator + $divisor - 1, $divisor);
    }

    private static function microsToDecimalString(int $micros, int $scale): string
    {
        $negative = $micros < 0;
        $micros = abs($micros);

        if ($scale === self::LOG_SCALE) {
            $whole = intdiv($micros, self::MICRO_FACTOR);
            $fraction = intdiv($micros % self::MICRO_FACTOR, 100);

            return ($negative ? '-' : '').sprintf('%d.%04d', $whole, $fraction);
        }

        $whole = intdiv($micros, self::MICRO_FACTOR);
        $fraction = $micros % self::MICRO_FACTOR;

        return ($negative ? '-' : '').sprintf('%d.%06d', $whole, $fraction);
    }
}
