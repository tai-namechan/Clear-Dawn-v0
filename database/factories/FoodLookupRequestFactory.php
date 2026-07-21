<?php

namespace Database\Factories;

use App\Enums\FoodLookupStatus;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodLookupRequest>
 */
class FoodLookupRequestFactory extends Factory
{
    protected $model = FoodLookupRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'barcode' => '4901234567894',
            'barcode_type' => 'ean13',
            'status' => FoodLookupStatus::Pending,
            'source' => null,
            'result' => null,
            'error_code' => null,
            'temp_image_path' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    public function found(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FoodLookupStatus::Found,
            'source' => 'openfoodfacts',
            'result' => [
                'name' => 'テスト食品',
                'serving_label' => '100g',
                'per' => '100g',
                'kcal' => 250.0,
                'protein_g' => 10.0,
                'fat_g' => 8.0,
                'carb_g' => 30.0,
            ],
        ]);
    }

    public function notFound(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FoodLookupStatus::NotFound,
            'source' => 'openfoodfacts',
        ]);
    }

    public function ocrPending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FoodLookupStatus::OcrPending,
            'source' => null,
            'result' => null,
            'temp_image_path' => 'food-label-ocr/1/'.fake()->uuid().'.jpg',
        ]);
    }

    public function withoutBarcode(): static
    {
        return $this->state(fn (array $attributes) => [
            'barcode' => null,
            'barcode_type' => null,
        ]);
    }
}
