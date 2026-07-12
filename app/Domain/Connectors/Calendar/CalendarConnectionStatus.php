<?php

namespace App\Domain\Connectors\Calendar;

enum CalendarConnectionStatus: string
{
    case Disconnected = 'disconnected';
    case Syncing = 'syncing';
    case Connected = 'connected';
    case Error = 'error';
    case Revoking = 'revoking';
    case Mock = 'mock';
}
