<?php

namespace App\Domain\Kioku\Embedding;

use RuntimeException;

final class EmbeddingFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $permanent = false,
        public readonly string $errorCode = 'embedding_failed',
    ) {
        parent::__construct($message);
    }
}
