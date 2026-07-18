<?php

namespace Database\Factories;

use App\Enums\ModuleKey;
use App\Models\User;
use App\Models\UserModuleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserModuleSetting>
 */
class UserModuleSettingFactory extends Factory
{
    protected $model = UserModuleSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_key' => ModuleKey::Strength,
            'is_enabled' => true,
        ];
    }
}
