<?php

namespace App\Services;

use App\Models\SymptomObservation;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecordSymptomObservationService
{
    /**
     * @param  array{
     *     body_region: string,
     *     symptom_kind: string,
     *     severity: int,
     *     is_new?: bool,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(User $user, Carbon $date, array $attributes): SymptomObservation
    {
        return $user->symptomObservations()->create([
            'observed_on' => $date->toDateString(),
            'body_region' => $attributes['body_region'],
            'symptom_kind' => $attributes['symptom_kind'],
            'severity' => $attributes['severity'],
            'is_new' => $attributes['is_new'] ?? false,
            'note' => $attributes['note'] ?? null,
        ]);
    }
}
