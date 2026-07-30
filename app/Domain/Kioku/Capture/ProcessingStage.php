<?php

namespace App\Domain\Kioku\Capture;

/**
 * Independent post-capture stages. Jobs remain separate per stage.
 */
enum ProcessingStage: string
{
    case Normalize = 'normalize';
    case Enrich = 'enrich';
    case Embedding = 'embedding';
}
