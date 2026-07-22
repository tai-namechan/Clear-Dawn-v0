<?php

namespace App\Http\Resources;

use App\Models\FoodItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FoodItem
 */
class FoodItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'serving_label' => $this->serving_label,
            'kcal' => (string) $this->kcal,
            'protein_g' => (string) $this->protein_g,
            'fat_g' => (string) $this->fat_g,
            'carb_g' => (string) $this->carb_g,
            'source' => $this->source,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
