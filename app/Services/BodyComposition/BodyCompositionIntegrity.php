<?php

namespace App\Services\BodyComposition;

/**
 * 体組成値の整合性判定。
 *
 * 不自然だから項目を入れ替えることはしない。通らない場合は要確認にする。
 */
final readonly class BodyCompositionIntegrity
{
    /**
     * @param  array<int, string>  $failures
     */
    public function __construct(
        public bool $hasCoreMeasurements,
        public bool $isConsistent,
        public array $failures,
    ) {}

    /**
     * 確定保存してよいか。
     */
    public function canConfirm(): bool
    {
        return $this->hasCoreMeasurements && $this->isConsistent;
    }
}
