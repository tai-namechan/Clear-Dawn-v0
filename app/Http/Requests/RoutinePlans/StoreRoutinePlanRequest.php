<?php

namespace App\Http\Requests\RoutinePlans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutinePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'title' => ['required', 'string', 'max:100'],
            'scheduled_on' => ['required', 'date'],
            'life_area_id' => [
                'nullable',
                'ulid',
                Rule::exists('life_areas', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'routine_id' => [
                'nullable',
                'ulid',
                Rule::exists('routines', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'note' => ['nullable', 'string'],
        ];
    }
}
