<?php

namespace Database\Factories;

use App\Models\ProgramAttachment;
use App\Models\ProgramVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramAttachment>
 */
class ProgramAttachmentFactory extends Factory
{
    protected $model = ProgramAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'program_version_id' => ProgramVersion::factory(),
            'title' => 'トレーニングプログラム PDF',
            'disk' => 'local',
            'path' => 'program-attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => fake()->numberBetween(10000, 5000000),
        ];
    }
}
