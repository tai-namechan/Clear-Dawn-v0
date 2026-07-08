<?php

namespace App\Http\Requests\MetricRecords;

use App\Enums\MetricValueType;
use App\Models\Metric;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDailyMetricsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'recorded_on' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.metric_key' => [
                'required',
                'string',
                Rule::exists('metrics', 'key'),
            ],
            'records.*.value' => ['required', 'numeric'],
            'records.*.life_area_id' => [
                'nullable',
                'ulid',
                Rule::exists('life_areas', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'records.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $records = $this->input('records', []);

            if (! is_array($records)) {
                return;
            }

            $metrics = Metric::query()
                ->whereIn('key', array_column($records, 'metric_key'))
                ->get()
                ->keyBy('key');

            foreach ($records as $index => $record) {
                if (! is_array($record)) {
                    continue;
                }

                $metric = $metrics->get($record['metric_key'] ?? '');

                if ($metric === null || ! array_key_exists('value', $record)) {
                    continue;
                }

                $value = $record['value'];

                if ($metric->value_type === MetricValueType::Integer) {
                    if (! is_numeric($value) || (float) $value != (int) $value) {
                        $validator->errors()->add("records.{$index}.value", 'The value must be an integer.');
                    }
                }

                if ($metric->value_type === MetricValueType::Scale15) {
                    if (
                        ! is_numeric($value)
                        || (float) $value != (int) $value
                        || (int) $value < 1
                        || (int) $value > 5
                    ) {
                        $validator->errors()->add("records.{$index}.value", 'The value must be an integer between 1 and 5.');
                    }
                }
            }
        });
    }
}
