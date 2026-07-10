<?php

namespace App\Http\Resources;

use App\Models\MealEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MealEntry
 */
class MealEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'food_item_id' => $this->food_item_id,
            'eaten_on' => $this->eaten_on->toDateString(),
            'meal_type' => $this->meal_type->value,
            'meal_type_label' => $this->meal_type->label(),
            'name' => $this->name,
            'quantity' => (string) $this->quantity,
            'kcal' => (string) $this->kcal,
            'protein_g' => (string) $this->protein_g,
            'fat_g' => (string) $this->fat_g,
            'carb_g' => (string) $this->carb_g,
            'note' => $this->note,
        ];
    }
}
