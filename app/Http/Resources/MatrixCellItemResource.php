<?php

namespace App\Http\Resources;

use App\Models\MatrixCellItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MatrixCellItem
 */
class MatrixCellItemResource extends JsonResource
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
            'title' => $this->title,
            'memo' => $this->memo,
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
        ];
    }
}
