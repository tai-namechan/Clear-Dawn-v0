<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyDecisionStatus: string
{
    case Planned = 'planned';
    case ActionRequired = 'action_required';
    case Executed = 'executed';
    case Reviewed = 'reviewed';
    case Canceled = 'canceled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
