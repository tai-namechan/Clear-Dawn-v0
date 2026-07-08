<?php

namespace App\Http\Resources;

use App\Models\RoutineBlockLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoutineBlockLog
 */
class RoutineBlockLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_session_step_id' => $this->routine_session_step_id,
            'block_number' => $this->block_number,
            'load_value' => $this->load_value !== null ? (string) $this->load_value : null,
            'load_unit' => $this->load_unit,
            'amount_value' => $this->amount_value !== null ? (string) $this->amount_value : null,
            'amount_unit' => $this->amount_unit,
            'memo' => $this->memo,
        ];
    }
}
