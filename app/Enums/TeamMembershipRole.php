<?php

namespace App\Enums;

enum TeamMembershipRole: string
{
    case Athlete = 'athlete';
    case Owner = 'owner';
    case Admin = 'admin';
    case HeadCoach = 'head_coach';
    case Coach = 'coach';
    case NutritionStaff = 'nutrition_staff';
    case ConditioningStaff = 'conditioning_staff';

    public function isStaff(): bool
    {
        return $this !== self::Athlete;
    }
}
