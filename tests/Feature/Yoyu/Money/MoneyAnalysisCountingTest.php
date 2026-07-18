<?php

namespace Tests\Feature\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Services\MoneyAnalysisQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyAnalysisCountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_excludes_card_payment_and_transfer(): void
    {
        $user = User::factory()->create();

        $this->createTx($user->id, MoneyTransactionKind::Purchase, 20_000);
        $this->createTx($user->id, MoneyTransactionKind::CardPayment, 20_000);
        $this->createTx($user->id, MoneyTransactionKind::Transfer, 5_000);
        $this->createTx($user->id, MoneyTransactionKind::Fee, 500);

        $result = app(MoneyAnalysisQuery::class)->analyze(
            $user,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        );

        $this->assertSame('20500', $result['total_spend_minor']);
    }

    public function test_refund_reduces_spend_total(): void
    {
        $user = User::factory()->create();

        $this->createTx($user->id, MoneyTransactionKind::Purchase, 10_000);
        $this->createTx($user->id, MoneyTransactionKind::Refund, 3_000);

        $result = app(MoneyAnalysisQuery::class)->analyze(
            $user,
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        );

        $this->assertSame('7000', $result['total_spend_minor']);
    }

    private function createTx(int $userId, MoneyTransactionKind $kind, int $amountMinor): void
    {
        MoneyTransaction::query()->withoutUserScope()->create([
            'user_id' => $userId,
            'direction' => MoneyDirection::Outflow,
            'kind' => $kind,
            'amount_minor' => $amountMinor,
            'currency_code' => 'JPY',
            'occurred_on' => now()->toDateString(),
            'description_raw' => 'fixture',
            'description_normalized' => 'fixture',
            'status' => MoneyTransactionStatus::Posted,
            'source' => MoneyTransactionSource::Manual,
        ]);
    }
}
