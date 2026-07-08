<?php

namespace App\Http\Resources;

use App\Models\TrainingPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TodayTrainingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{date: string, plans: Collection<int, TrainingPlan>} $data */
        $data = $this->resource;

        return [
            'date' => $data['date'],
            'plans' => TrainingPlanResource::collection($data['plans'])->resolve(),
        ];
    }
}
