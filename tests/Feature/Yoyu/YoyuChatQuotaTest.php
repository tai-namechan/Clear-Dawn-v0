<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuChatQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quota_exceeded_returns_safe_error_code_without_calling_provider(): void
    {
        config([
            'ai.anthropic.api_key' => 'test-key',
            'ai.limits.monthly_usd_per_user' => '0.000001',
        ]);
        Http::fake();

        $user = User::factory()->create();
        app(AiUsageLedger::class)->reserve(
            $user->id,
            'fill',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.000001'),
        );

        $this->actingAs($user)
            ->from(route('yoyu.home', ['tab' => 'chat']))
            ->post(route('yoyu.chat'), [
                'message' => '今日の段取りは？',
            ])
            ->assertRedirect(route('yoyu.home', ['tab' => 'chat']));

        Http::assertNothingSent();
        $this->assertSame(0, AiUsageRequest::query()->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('feature', 'yoyu.chat')
            ->count());

        $this->actingAs($user)
            ->get(route('yoyu.home', ['tab' => 'chat']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Index')
                ->where('chatErrorCode', 'quota_exceeded')
                ->where('chatReply', '今月のAI利用上限に達しました。原文の保存やタスク操作など、AI以外の機能は引き続き使えます。')
            );
    }

    public function test_quota_exceeded_does_not_block_task_creation(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);
        $user = User::factory()->create();
        app(AiUsageLedger::class)->reserve(
            $user->id,
            'fill',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.000001'),
        );

        $this->actingAs($user)
            ->post(route('yoyu.tasks.store'), [
                'title' => '上限でもタスク追加',
                'estimate_minutes' => 20,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_tasks', [
            'user_id' => $user->id,
            'title' => '上限でもタスク追加',
        ]);
    }
}
