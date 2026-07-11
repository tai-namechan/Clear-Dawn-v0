<?php

namespace App\Domain\Connectors\Google;

use RuntimeException;

/**
 * Refresh token is invalid/revoked (invalid_grant). Never retried; the user
 * must reconnect. The message stays generic — no provider body, no tokens.
 */
class ReauthorizationRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Google authorization is no longer valid; reconnection required.');
    }
}
