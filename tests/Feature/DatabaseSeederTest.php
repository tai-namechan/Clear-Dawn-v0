<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_no_user_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_seeding_still_loads_master_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('matrix_rows', []);
        $this->assertDatabaseHas('metrics', []);
    }
}
