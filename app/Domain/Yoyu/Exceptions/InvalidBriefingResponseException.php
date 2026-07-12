<?php

namespace App\Domain\Yoyu\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown only when AI briefing output fails schema/validation checks.
 * Must not wrap factory/DB/programming failures.
 */
final class InvalidBriefingResponseException extends RuntimeException
{
    public function __construct(string $message = 'Invalid briefing AI response.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
