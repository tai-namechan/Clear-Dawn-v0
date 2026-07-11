<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_yoyu(): void
    {
        $this->get(route('yoyu.home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_yoyu_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Index')
                ->where('currentProduct', 'yoyu')
                ->has('calendar')
                ->has('clearDawnHand')
            );
    }

    public function test_user_can_create_task(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.tasks.store'), [
                'title' => 'READMEを直す',
                'estimate_minutes' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_tasks', [
            'user_id' => $user->id,
            'title' => 'READMEを直す',
            'status' => 'planned',
        ]);
    }

    public function test_mind_dump_creates_memory_and_focus_item(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.focus.store'), [
                'text' => '頭の中のモヤモヤ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('memories', [
            'user_id' => $user->id,
            'raw_content' => '頭の中のモヤモヤ',
            'source_type' => 'yoyu',
        ]);
        $this->assertDatabaseCount('yoyu_focus_items', 1);
        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_user_cannot_update_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = YoyuTask::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patch(route('yoyu.tasks.update', $task), ['status' => 'done'])
            ->assertNotFound();
    }

    public function test_regenerate_briefing_dispatches_job_and_sets_generating_status(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.briefing.regenerate'))
            ->assertRedirect(route('yoyu.home', ['tab' => 'today']));

        $this->assertDatabaseHas('yoyu_briefings', [
            'user_id' => $user->id,
            'status' => 'generating',
        ]);

        Bus::assertDispatched(GenerateYoyuBriefingJob::class);
    }

    public function test_yoyu_home_shares_briefing_status(): void
    {
        $user = User::factory()->create();
        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'body' => 'テストブリーフィング',
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('briefing', 'テストブリーフィング')
                ->where('briefingStatus', 'ready')
            );
    }
}
