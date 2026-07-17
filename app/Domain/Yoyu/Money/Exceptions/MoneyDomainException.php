<?php

namespace App\Domain\Yoyu\Money\Exceptions;

use RuntimeException;
use Throwable;

final class MoneyDomainException extends RuntimeException
{
    /**
     * @param  list<string>  $blockers
     */
    public function __construct(
        string $message,
        public readonly array $blockers = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
