<?php

namespace App\Http\Resources;

use App\Models\Metric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Metric
 */
class MetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'unit' => $this->unit,
            'value_type' => $this->value_type->value,
            'sort_order' => $this->sort_order,
        ];
    }
}
