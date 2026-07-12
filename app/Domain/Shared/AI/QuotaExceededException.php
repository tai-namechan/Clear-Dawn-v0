<?php

namespace App\Domain\Shared\AI;

use RuntimeException;

final class QuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'AI monthly usage limit exceeded.',
        public readonly string $failureCode = 'quota_exceeded',
    ) {
        parent::__construct($message);
    }
}
