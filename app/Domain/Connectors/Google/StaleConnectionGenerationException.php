<?php

namespace App\Domain\Connectors\Google;

use RuntimeException;

/**
 * The connector's connection_version advanced while a job was in flight.
 * Callers must abort without writing cache or status for the old generation.
 */
final class StaleConnectionGenerationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Calendar connector connection_version no longer matches.');
    }
}
