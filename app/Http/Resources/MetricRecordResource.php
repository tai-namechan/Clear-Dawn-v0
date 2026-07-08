<?php

namespace App\Http\Resources;

use App\Models\MetricRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MetricRecord
 */
class MetricRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'metric_id' => $this->metric_id,
            'life_area_id' => $this->life_area_id,
            'recorded_on' => $this->recorded_on->toDateString(),
            'value' => (string) $this->value,
            'note' => $this->note,
            'metric' => MetricResource::make($this->whenLoaded('metric')),
            'life_area' => LifeAreaResource::make($this->whenLoaded('lifeArea')),
        ];
    }
}
