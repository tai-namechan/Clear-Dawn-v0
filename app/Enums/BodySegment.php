<?php

namespace App\Enums;

/**
 * 体組成の部位キー。
 */
enum BodySegment: string
{
    case LeftArm = 'left_arm';
    case RightArm = 'right_arm';
    case Torso = 'torso';
    case LeftLeg = 'left_leg';
    case RightLeg = 'right_leg';

    /**
     * 保存用の値一覧。
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
