<?php

namespace Tests\Feature\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyImportStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyImport;
use App\Domain\Yoyu\Money\Models\MoneyImportRow;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Services\MoneySetupService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoneyCsvImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "date,description,amount\n2026-07-01,スーパー,1200\n2026-07-02,給与,-50000\n";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array{0: User, 1: MoneyAccount}
     */
    private function createUserWithAccount(): array
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

        app(MoneySetupService::class)->setup($user, [
            'timezone' => 'Asia/Tokyo',
            'minimum_living_budget_minor' => 20_000,
            'safety_buffer_minor' => 10_000,
        ]);

        $account = MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => 'メイン口座',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 100_000,
            'available_balance_minor' => 100_000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        return [$user, $account];
    }

    private function runImportFlow(User $user, MoneyAccount $account): MoneyImport
    {
        $this->actingAs($user)
            ->post(route('yoyu.money.imports.store'), [
                'account_id' => $account->id,
                'file' => UploadedFile::fake()->createWithContent('bank.csv', self::CSV),
            ])
            ->assertRedirect();

        /** @var MoneyImport $import */
        $import = MoneyImport::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('yoyu.money.imports.configure', $import), [
                'date_column' => 'date',
                'description_column' => 'description',
                'amount_column' => 'amount',
                'amount_sign' => 'expense_positive',
                'has_header' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('yoyu.money.imports.execute', $import))
            ->assertRedirect();

        return $import->refresh();
    }

    public function test_upload_configure_execute_creates_transactions(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $import = $this->runImportFlow($user, $account);

        $this->assertSame(MoneyImportStatus::Completed, $import->status);
        $this->assertSame(2, (int) $import->accepted_count);
        $this->assertSame(0, (int) $import->rejected_count);

        $transactions = MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('import_id', $import->id)
            ->get();

        $this->assertCount(2, $transactions);
        $this->assertSame(1200, (int) $transactions->firstWhere('direction', 'outflow')->amount_minor);
        $this->assertSame(50000, (int) $transactions->firstWhere('direction', 'inflow')->amount_minor);
    }

    public function test_reimporting_same_csv_skips_strong_duplicates(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $this->runImportFlow($user, $account);
        $second = $this->runImportFlow($user, $account);

        $this->assertSame(MoneyImportStatus::Completed, $second->status);
        $this->assertSame(0, (int) $second->accepted_count);
        $this->assertSame(2, (int) $second->duplicate_count);

        $this->assertSame(
            2,
            MoneyTransaction::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->whereNull('voided_at')
                ->count(),
        );
    }

    public function test_rollback_voids_imported_transactions(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $import = $this->runImportFlow($user, $account);

        $this->actingAs($user)
            ->from(route('yoyu.money.imports.index'))
            ->post(route('yoyu.money.imports.rollback', $import))
            ->assertRedirect();

        $this->assertSame(MoneyImportStatus::RolledBack, $import->refresh()->status);
        $this->assertSame(
            0,
            MoneyTransaction::query()
                ->withoutUserScope()
                ->where('import_id', $import->id)
                ->whereNull('voided_at')
                ->count(),
        );
    }

    public function test_user_cannot_configure_another_users_import(): void
    {
        [$owner, $account] = $this->createUserWithAccount();
        $import = $this->runImportFlow($owner, $account);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('yoyu.money.imports.configure', $import), [
                'date_column' => 'date',
                'amount_column' => 'amount',
            ])
            ->assertNotFound();

        $this->assertSame(0, MoneyImportRow::query()->withoutUserScope()->where('user_id', $other->id)->count());
    }
}
