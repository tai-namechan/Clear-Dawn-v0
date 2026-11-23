<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed master data only. Accounts are created through registration in
     * every environment, so no user with a known password is ever seeded
     * (docs/dev/environments.md).
     */
    public function run(): void
    {
        $this->call(MatrixRowSeeder::class);
        $this->call(MetricSeeder::class);
    }
}
