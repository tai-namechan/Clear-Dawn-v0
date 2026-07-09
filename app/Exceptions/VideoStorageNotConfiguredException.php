<?php

namespace App\Exceptions;

use RuntimeException;

class VideoStorageNotConfiguredException extends RuntimeException
{
    public static function missingBucket(): self
    {
        return new self(
            '動画ストレージが未設定です。Laravel Cloud で Object Storage バケットを環境に接続し、再デプロイしてください。',
        );
    }
}
