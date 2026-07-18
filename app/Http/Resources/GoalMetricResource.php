<?php

namespace App\Http\Resources;

use App\Models\GoalMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoalMetric
 */
class GoalMetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goal_id' => $this->goal_id,
            'metric_id' => $this->metric_id,
            'metric' => $this->whenLoaded('metric', fn () => [
                'id' => $this->metric->id,
                'key' => $this->metric->key,
                'label' => $this->metric->label,
                'unit' => $this->metric->unit,
                'is_advanced' => $this->metric->is_advanced,
            ]),
            'baseline_value' => $this->baseline_value,
            'target_value' => $this->target_value,
            'target_low' => $this->target_low,
            'target_high' => $this->target_high,
            'direction' => $this->direction?->value,
            'note' => $this->note,
            'sort_order' => $this->sort_order,
        ];
    }
}
