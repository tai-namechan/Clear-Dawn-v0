<?php

namespace App\Support;

use App\Enums\ProgramStepKind;
use App\Enums\StepPurpose;

class ProgramStepKindMapper
{
    public static function toStepPurpose(ProgramStepKind $kind): StepPurpose
    {
        return match ($kind) {
            ProgramStepKind::Preparation => StepPurpose::Prep,
            ProgramStepKind::Movement => StepPurpose::Movement,
            ProgramStepKind::Power => StepPurpose::Power,
            ProgramStepKind::Throwing => StepPurpose::Practice,
            ProgramStepKind::Strength, ProgramStepKind::Accessory => StepPurpose::Strength,
            ProgramStepKind::ArmCare => StepPurpose::Care,
            ProgramStepKind::Conditioning, ProgramStepKind::Cooldown => StepPurpose::Other,
        };
    }
}
