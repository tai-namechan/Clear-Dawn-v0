<?php

namespace App\Enums;

enum RecommendationStatus: string
{
    case Pending = 'pending';
    case Decided = 'decided';
    case Expired = 'expired';
}
