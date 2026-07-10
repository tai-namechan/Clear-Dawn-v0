<?php

namespace App\Http\Resources;

use App\Models\NutritionGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NutritionGoal
 */
class NutritionGoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kcal' => (string) $this->kcal,
            'protein_g' => (string) $this->protein_g,
            'fat_g' => (string) $this->fat_g,
            'carb_g' => (string) $this->carb_g,
        ];
    }
}
