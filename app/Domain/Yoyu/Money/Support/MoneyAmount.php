<?php

namespace App\Domain\Yoyu\Money\Support;

use InvalidArgumentException;

/**
 * Money amount in minor currency units (e.g. yen for JPY).
 *
 * Persist and exchange over HTTP/TypeScript as strings to avoid precision loss.
 * PHP arithmetic may use {@see toInt()} within safe integer range for JPY.
 */
final class MoneyAmount
{
    private function __construct(
        private readonly string $minor,
    ) {}

    public static function ofMinor(string|int $minor): self
    {
        $value = is_int($minor) ? (string) $minor : $minor;

        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('MoneyAmount minor must be digits with an optional leading minus.');
        }

        if ($value === '-0') {
            $value = '0';
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->minor;
    }

    /**
     * Integer minor units for PHP-side calculation.
     * JavaScript / HTTP clients must keep amounts as strings.
     */
    public function toInt(): int
    {
        return (int) $this->minor;
    }

    public function add(self $other): self
    {
        return self::ofMinor(bcadd($this->minor, $other->minor, 0));
    }

    public function sub(self $other): self
    {
        return self::ofMinor(bcsub($this->minor, $other->minor, 0));
    }

    /**
     * Assert this value is non-negative (amount bodies; balances may be negative).
     */
    public function assertNonNegative(): self
    {
        if (bccomp($this->minor, '0', 0) < 0) {
            throw new InvalidArgumentException('MoneyAmount must be non-negative.');
        }

        return $this;
    }
}
