<?php

namespace App\Http\Requests\RoutinePlanSteps;

use App\Enums\StepPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoutinePlanStepRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'routine_item_id' => [
                'required',
                'ulid',
                Rule::exists('routine_items', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'title' => ['nullable', 'string', 'max:100'],
            'video_id' => [
                'nullable',
                'ulid',
                Rule::exists('videos', 'id')->where(fn ($query) => $query->where('user_id', $userId)),
            ],
            'purpose' => ['required', Rule::enum(StepPurpose::class)],
            'target_load' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'load_unit' => ['nullable', 'string', 'max:20'],
            'target_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'amount_unit' => ['nullable', 'string', 'max:20'],
            'target_blocks' => ['required', 'integer', 'min:1', 'max:99'],
            'rest_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'routine_item_id.required' => '実施項目を選択または作成してください。',
            'routine_item_id.exists' => '選択した実施項目が見つかりません。',
            'purpose.required' => '目的を選択してください。',
            'target_blocks.required' => 'セット数を入力してください。',
            'target_blocks.min' => 'セット数は1以上で入力してください。',
            'target_load.numeric' => '重量は数値で入力してください。',
            'target_amount.numeric' => '回数・時間・距離は数値で入力してください。',
            'rest_seconds.integer' => '休憩は整数で入力してください。',
            'note.max' => 'メモは500文字以内で入力してください。',
            'title.max' => 'ステップ名は100文字以内で入力してください。',
            'video_id.exists' => '選択した動画が見つかりません。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'routine_item_id' => '実施項目',
            'title' => 'ステップ名',
            'purpose' => '目的',
            'target_blocks' => 'セット数',
            'target_load' => '重量',
            'target_amount' => '回数・量',
            'rest_seconds' => '休憩',
            'note' => 'メモ',
            'video_id' => '動画',
        ];
    }
}
