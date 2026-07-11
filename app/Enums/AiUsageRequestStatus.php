<?php

namespace App\Enums;

enum AiUsageRequestStatus: string
{
    case Reserved = 'reserved';
    case InFlight = 'in_flight';
    case Settled = 'settled';
    case Released = 'released';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Settled, self::Released, self::Expired => true,
            self::Reserved, self::InFlight => false,
        };
    }
}
