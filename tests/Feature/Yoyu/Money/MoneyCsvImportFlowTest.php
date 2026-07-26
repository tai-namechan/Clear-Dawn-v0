<?php

namespace Tests\Feature\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyImportStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyImport;
use App\Domain\Yoyu\Money\Models\MoneyImportRow;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Services\MoneyCsvImportService;
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

    /**
     * 監査 C-4 の回帰テスト。
     *
     * retry_after < ジョブ timeout のとき、実行中のジョブがキューに再可視化され
     * 同じ import が二重に処理されうる（ShouldBeUnique は dispatch 時のロックなので
     * これを防げない）。二重処理されると同じ CSV 行から2件の取引が作られ、
     * 金額が二重計上される。
     *
     * processImport() はキュー設定に依存せず、それ自体が冪等でなければならない。
     */
    public function test_process_import_is_idempotent_when_executed_twice(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $import = $this->runImportFlow($user, $account);
        $this->assertSame(MoneyImportStatus::Completed, $import->status);

        $transactionsAfterFirst = MoneyTransaction::query()
            ->withoutUserScope()
            ->where('import_id', $import->id)
            ->count();
        $this->assertSame(2, $transactionsAfterFirst);

        // 二重取得されたワーカーの再実行を模す。
        app(MoneyCsvImportService::class)->processImport($import->refresh());

        $this->assertSame(
            $transactionsAfterFirst,
            MoneyTransaction::query()->withoutUserScope()->where('import_id', $import->id)->count(),
            '完了済み import の再処理で取引が増えてはならない（二重計上）。',
        );

        $this->assertSame(
            2,
            MoneyImportRow::query()->withoutUserScope()->where('import_id', $import->id)->count(),
        );

        $import->refresh();
        $this->assertSame(MoneyImportStatus::Completed, $import->status);
        $this->assertSame(2, (int) $import->accepted_count);
    }

    /**
     * 監査 C-3 の回帰テスト（ディスク設定の外出し部分）。
     *
     * 以前は private const DISK = 'local' のハードコードで、env による差し替えが
     * できなかった。Laravel Cloud では Web とキューがファイルシステムを共有しないため、
     * 本番では取り込みが必ず失敗していた。
     *
     * 注意: Storage::fake() はローカルドライバのため、本テストは
     * 「path() を持たないディスクでも動くこと」までは証明しない。
     * そちらは Storage::path() / SplFileObject への依存をコードから完全に除去し、
     * readStream() + fgetcsv() に置き換えたという構造的変更で担保している
     * （grep で self::DISK / SplFileObject が 0 件であることを確認済み）。
     *
     * 本テストが固定するのは「ディスクが設定で差し替わり、
     * 既定の local へフォールバックしないこと」である。
     */
    public function test_import_uses_the_configured_disk_instead_of_hardcoded_local(): void
    {
        Storage::fake('object-storage');
        config()->set('yoyu.money.import.disk', 'object-storage');

        [$user, $account] = $this->createUserWithAccount();

        $import = $this->runImportFlow($user, $account);

        $this->assertSame(MoneyImportStatus::Completed, $import->status);
        $this->assertSame(2, (int) $import->accepted_count);
        $this->assertSame(
            2,
            MoneyTransaction::query()->withoutUserScope()->where('import_id', $import->id)->count(),
        );

        // 原本が指定ディスク側に置かれ、local には置かれていないこと。
        Storage::disk('object-storage')->assertExists((string) $import->source_storage_path);
        Storage::disk('local')->assertMissing((string) $import->source_storage_path);
    }

    /**
     * 監査 M-1 の回帰テスト。
     *
     * delimiter は max:8 の自由文字列だったため2文字以上を送ると
     * setCsvControl が ValueError を投げて 500 になっていた。
     * encoding も同様で、未知の値は mb_convert_encoding が ValueError を投げる
     * （@ では抑制できない。PHP 8 では警告ではなく例外のため）。
     *
     * 500 ではなくバリデーションエラーとして返し、ユーザーが自力で直せる状態にする。
     */
    public function test_invalid_csv_delimiter_is_rejected_by_validation(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $this->actingAs($user)
            ->post(route('yoyu.money.imports.store'), [
                'account_id' => $account->id,
                'file' => UploadedFile::fake()->createWithContent('bank.csv', self::CSV),
            ])
            ->assertRedirect();

        /** @var MoneyImport $import */
        $import = MoneyImport::query()->withoutUserScope()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->from(route('yoyu.money.imports.create'))
            ->post(route('yoyu.money.imports.configure', $import), [
                'date_column' => 'date',
                'amount_column' => 'amount',
                'delimiter' => '||',
            ])
            ->assertSessionHasErrors('delimiter');

        $this->actingAs($user)
            ->from(route('yoyu.money.imports.create'))
            ->post(route('yoyu.money.imports.configure', $import), [
                'date_column' => 'date',
                'amount_column' => 'amount',
                'encoding' => 'NOT-A-CHARSET',
            ])
            ->assertSessionHasErrors('encoding');
    }

    /**
     * UI は amount_sign に 'signed' を送る。MoneyCsvNormalizer は
     * 'income_positive' 以外を expense_positive として安全に既定処理するため、
     * ここを許可リスト化すると不具合を直さずに既存 UI を壊すだけになる。
     */
    public function test_configure_still_accepts_the_amount_sign_the_ui_sends(): void
    {
        [$user, $account] = $this->createUserWithAccount();

        $this->actingAs($user)
            ->post(route('yoyu.money.imports.store'), [
                'account_id' => $account->id,
                'file' => UploadedFile::fake()->createWithContent('bank.csv', self::CSV),
            ])
            ->assertRedirect();

        /** @var MoneyImport $import */
        $import = MoneyImport::query()->withoutUserScope()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('yoyu.money.imports.configure', $import), [
                'date_column' => 'date',
                'description_column' => 'description',
                'amount_column' => 'amount',
                'amount_sign' => 'signed',
                'encoding' => 'UTF-8',
                'delimiter' => ',',
                'has_header' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
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
