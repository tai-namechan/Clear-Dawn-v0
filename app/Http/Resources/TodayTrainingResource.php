<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TodayTrainingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{date: string, plans: \Illuminate\Support\Collection} $data */
        $data = $this->resource;

        return [
            'date' => $data['date'],
            'plans' => TrainingPlanResource::collection($data['plans'])->resolve(),
        ];
    }
}
