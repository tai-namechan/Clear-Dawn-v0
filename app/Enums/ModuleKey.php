<?php

namespace App\Enums;

/**
 * 有効/無効を切り替えられる領域モジュール（ADR-0011）。
 * プラグイン基盤は作らず、user_module_settings + 画面の表示条件のみで制御する。
 */
enum ModuleKey: string
{
    case Strength = 'strength';
    case Throwing = 'throwing';
    case Drills = 'drills';
    case ArmCare = 'arm_care';
    case ConditionRecovery = 'condition_recovery';
    case PainNeuro = 'pain_neuro';
    case NutritionBody = 'nutrition_body';
    case AerobicZone2 = 'aerobic_zone2';
    case YogaPilates = 'yoga_pilates';
    case Sleep = 'sleep';
    case BodyReadiness = 'body_readiness';
    case CognitiveReadiness = 'cognitive_readiness';
}
