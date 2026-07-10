<?php

namespace Database\Factories\Domain\Yoyu;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Yoyu\Models\YoyuFocusItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoyuFocusItem>
 */
class YoyuFocusItemFactory extends Factory
{
    protected $model = YoyuFocusItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'memory_id' => Memory::factory(),
            'status' => 'open',
        ];
    }
}
