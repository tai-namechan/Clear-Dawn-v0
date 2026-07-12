<?php

namespace App\Http\Resources\Kioku;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Memory
 */
class MemoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Memory $memory */
        $memory = $this->resource;
        $registry = app(MemoryTypeRegistry::class);
        $type = $registry->tryGet($memory->memory_type);

        return [
            'id' => $memory->id,
            'client_capture_id' => $memory->client_capture_id,
            'source_type' => $memory->source_type,
            'memory_type' => $memory->memory_type,
            'memory_type_label' => $type?->label(),
            'title' => $memory->title,
            'raw_content' => $memory->raw_content,
            'transcript_text' => $memory->transcript_text,
            'summary' => $memory->summary,
            'structured_data' => $memory->structured_data,
            'display_fields' => $type?->displayFields() ?? [],
            'tags' => $memory->tags ?? [],
            'captured_at' => $memory->captured_at?->toIso8601String(),
            'importance' => $memory->importance,
            'sensitive' => $memory->sensitive,
            'status' => $memory->status,
            'transcription_status' => $memory->transcription_status,
            'referenced_count' => $memory->referenced_count,
        ];
    }
}
