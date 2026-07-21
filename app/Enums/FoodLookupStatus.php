<?php

namespace App\Enums;

/**
 * 食品バーコード/成分表照合リクエストの状態（設計 §13）。
 */
enum FoodLookupStatus: string
{
    case Pending = 'pending';
    case OcrPending = 'ocr_pending';
    case AiPending = 'ai_pending';
    case Found = 'found';
    case NotFound = 'not_found';
    case Failed = 'failed';
}
