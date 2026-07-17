<?php

namespace App\Domain\Yoyu\Money\Data;

/**
 * Deterministic margin calculation result for 「お金の余裕」.
 *
 * Amount fields are minor-unit strings for safe transport to the client.
 */
final readonly class MarginResult
{
    /**
     * @param  list<string>  $missingSettings
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $breakdown
     */
    public function __construct(
        public string $fundsMinor,
        public string $confirmedIncomeMinor,
        public string $confirmedOutflowMinor,
        public string $uncertainReserveMinor,
        public string $livingReserveMinor,
        public string $safetyBufferMinor,
        public string $projectedCashMinor,
        public string $projectedMarginMinor,
        public string $safeToSpendMinor,
        public string $shortfallMinor,
        public string $formulaVersion,
        public string $asOf,
        public string $horizonEnd,
        public bool $isComplete,
        public array $missingSettings,
        public array $warnings,
        public array $breakdown,
    ) {}

    /**
     * @return array{
     *     funds_minor: string,
     *     confirmed_income_minor: string,
     *     confirmed_outflow_minor: string,
     *     uncertain_reserve_minor: string,
     *     living_reserve_minor: string,
     *     safety_buffer_minor: string,
     *     projected_cash_minor: string,
     *     projected_margin_minor: string,
     *     safe_to_spend_minor: string,
     *     shortfall_minor: string,
     *     formula_version: string,
     *     as_of: string,
     *     horizon_end: string,
     *     is_complete: bool,
     *     missing_settings: list<string>,
     *     warnings: list<string>,
     *     breakdown: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'funds_minor' => $this->fundsMinor,
            'confirmed_income_minor' => $this->confirmedIncomeMinor,
            'confirmed_outflow_minor' => $this->confirmedOutflowMinor,
            'uncertain_reserve_minor' => $this->uncertainReserveMinor,
            'living_reserve_minor' => $this->livingReserveMinor,
            'safety_buffer_minor' => $this->safetyBufferMinor,
            'projected_cash_minor' => $this->projectedCashMinor,
            'projected_margin_minor' => $this->projectedMarginMinor,
            'safe_to_spend_minor' => $this->safeToSpendMinor,
            'shortfall_minor' => $this->shortfallMinor,
            'formula_version' => $this->formulaVersion,
            'as_of' => $this->asOf,
            'horizon_end' => $this->horizonEnd,
            'is_complete' => $this->isComplete,
            'missing_settings' => $this->missingSettings,
            'warnings' => $this->warnings,
            'breakdown' => $this->breakdown,
        ];
    }
}
