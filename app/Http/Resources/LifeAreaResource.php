<?php

namespace App\Http\Resources;

use App\Models\LifeArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LifeArea
 */
class LifeAreaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color->value,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
