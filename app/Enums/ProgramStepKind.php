<?php

namespace App\Enums;

/**
 * セッション内ブロックの種別（順序依存: 例 投球は strength より前）。
 */
enum ProgramStepKind: string
{
    case Preparation = 'preparation';
    case Movement = 'movement';
    case Power = 'power';
    case Throwing = 'throwing';
    case Strength = 'strength';
    case Accessory = 'accessory';
    case ArmCare = 'arm_care';
    case Conditioning = 'conditioning';
    case Cooldown = 'cooldown';
}
