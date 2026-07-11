<?php

namespace App\Domain\Connectors\Google;

use RuntimeException;

class UnauthorizedGoogleRequestException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Google API rejected the access token.');
    }
}
