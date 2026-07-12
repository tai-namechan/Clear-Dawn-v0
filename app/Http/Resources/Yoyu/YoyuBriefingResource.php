<?php

namespace App\Http\Resources\Yoyu;

use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Services\BriefingStructuredDataFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin YoyuBriefing
 */
class YoyuBriefingResource extends JsonResource
{
    /**
     * @return array{
     *     body: string|null,
     *     status: string|null,
     *     structured: array<string, mixed>|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var YoyuBriefing|null $briefing */
        $briefing = $this->resource;

        if ($briefing === null) {
            return [
                'body' => null,
                'status' => null,
                'structured' => null,
            ];
        }

        $structured = is_array($briefing->structured_data) ? $briefing->structured_data : null;
        if (
            is_array($structured)
            && (int) ($structured['schema_version'] ?? 0) !== BriefingStructuredDataFactory::SCHEMA_VERSION
        ) {
            $structured = null;
        }

        return [
            'body' => $briefing->body,
            'status' => $briefing->status,
            'structured' => $structured,
        ];
    }
}
