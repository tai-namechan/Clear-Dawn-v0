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
            'brand' => $this->brand,
            'nutrition_basis' => $this->nutrition_basis,
            'basis_amount' => $this->basis_amount !== null ? (string) $this->basis_amount : null,
            'basis_unit' => $this->basis_unit,
            'package_amount' => $this->package_amount !== null ? (string) $this->package_amount : null,
            'package_unit' => $this->package_unit,
            'is_favorite' => (bool) $this->is_favorite,
            'confirmation_status' => $this->confirmation_status,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
