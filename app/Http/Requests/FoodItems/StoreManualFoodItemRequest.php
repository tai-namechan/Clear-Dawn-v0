<?php

namespace App\Http\Requests\FoodItems;

use App\Enums\MealType;
use App\Enums\NutritionBasis;
use App\Support\BarcodeNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * OFF未検出後の手入力登録、または「今回だけ直接入力」。
 * save_mode=food_only|food_and_meal|one_off
 */
class StoreManualFoodItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $saveMode = (string) $this->input('save_mode', 'food_only');
        $needsMeal = in_array($saveMode, ['food_and_meal', 'one_off'], true);
        $needsFood = in_array($saveMode, ['food_only', 'food_and_meal'], true);

        return [
            'save_mode' => ['required', Rule::in(['food_only', 'food_and_meal', 'one_off'])],
            'name' => ['required', 'string', 'max:100'],
            'serving_label' => [$needsFood ? 'required' : 'nullable', 'string', 'max:50'],
            'kcal' => ['required', 'numeric', 'min:0', 'max:9999'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'carb_g' => ['required', 'numeric', 'min:0', 'max:999'],
            'barcode' => ['nullable', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:100'],
            'nutrition_basis' => ['nullable', 'string', Rule::in(NutritionBasis::values())],
            'basis_amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'basis_unit' => ['nullable', 'string', 'max:16'],
            'package_amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'package_unit' => ['nullable', 'string', 'max:16'],
            'eaten_on' => [$needsMeal ? 'required' : 'nullable', 'date'],
            'meal_type' => [$needsMeal ? 'required' : 'nullable', Rule::in(MealType::values())],
            'quantity' => [$needsMeal ? 'required' : 'nullable', 'numeric', 'min:0.1', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->input('barcode');

            if ($raw === null || trim((string) $raw) === '') {
                return;
            }

            $normalized = app(BarcodeNormalizer::class)->normalize((string) $raw);

            if ($normalized === null) {
                $validator->errors()->add(
                    'barcode',
                    'バーコードの形式が正しくありません（EAN-8 / UPC-A / EAN-13）。',
                );
            }
        });
    }
}
