<?php

namespace App\Providers;

use App\Domain\Kioku\Transcription\FakeTranscriptionGateway;
use App\Domain\Kioku\Transcription\NullTranscriptionGateway;
use App\Domain\Kioku\Transcription\OpenAiTranscriptionGateway;
use App\Domain\Kioku\Transcription\TranscriptionGateway;
use App\Models\MatrixCellItem;
use App\Models\RoutineSession;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Speech-to-text providers (docs/product/kioku-quick-capture.md §12,
        // kioku-final-remaining-implementation.md §3). 'none' must never fake
        // a success — jobs guard on the provider before transcribing — and an
        // unknown provider fails loudly instead of silently degrading.
        $this->app->bind(TranscriptionGateway::class, function (Application $app): TranscriptionGateway {
            $provider = (string) config('kioku.transcription.provider', 'none');

            return match ($provider) {
                'none' => new NullTranscriptionGateway,
                'fake' => new FakeTranscriptionGateway,
                'openai' => $app->make(OpenAiTranscriptionGateway::class),
                default => throw new RuntimeException(
                    "Unknown transcription provider [{$provider}] (KIOKU_TRANSCRIPTION_PROVIDER)."
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // activity_logs は不変ログのため、subject_type にクラス名ではなく
        // 安定した alias を保存する（クラスのリネームでログが壊れないようにする）
        Relation::enforceMorphMap([
            'matrix_cell_item' => MatrixCellItem::class,
            'routine_session' => RoutineSession::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // N+1 と fillable 漏れをローカル開発時点で検出する（監査 M-2）。
        // .cursor/rules/sql-memory-performance.mdc が N+1 防止を規約として
        // 掲げている一方、それを機械的に強制する仕組みが無かった。
        //
        // 有効化の範囲を local に限定している理由:
        //   - 本番: 未検出の遅延ロードが 500 としてユーザーに到達するより、
        //     従来どおり動作させる方が安全
        //   - testing: 既存 904 テストに潜在 N+1 があると一斉に落ち、
        //     本監査の Critical 修正の検証結果が埋もれる。CI 稼働後、
        //     影響を確認できる状態で testing へ広げる（ロードマップ Phase 6）
        $strictModels = app()->environment('local');

        Model::preventLazyLoading($strictModels);
        Model::preventSilentlyDiscardingAttributes($strictModels);

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
