<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TodayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{date: string, plans: Collection<int, \App\Models\RoutinePlan>} $data */
        $data = $this->resource;

        return [
            'date' => $data['date'],
            'plans' => RoutinePlanResource::collection($data['plans'])->resolve(),
        ];
    }
}
