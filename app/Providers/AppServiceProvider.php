<?php

namespace App\Providers;

use App\Domain\Kioku\Capture\Adapters\AudioFileImportCaptureAdapter;
use App\Domain\Kioku\Capture\Adapters\BrowserVoiceCaptureAdapter;
use App\Domain\Kioku\Capture\Adapters\CaptureAdapterRegistry;
use App\Domain\Kioku\Capture\Adapters\IosShortcutCaptureAdapter;
use App\Domain\Kioku\Capture\Adapters\WebTextCaptureAdapter;
use App\Domain\Kioku\Capture\CanonicalRawStore;
use App\Domain\Kioku\Capture\DefaultMemoryProcessingPipeline;
use App\Domain\Kioku\Capture\MemoryProcessingPipeline;
use App\Domain\Kioku\Capture\Normalizers\AudioTranscriptionNormalizer;
use App\Domain\Kioku\Capture\Normalizers\RawNormalizerRegistry;
use App\Domain\Kioku\Capture\Normalizers\TextRawNormalizer;
use App\Domain\Kioku\Capture\Normalizers\UrlContentNormalizer;
use App\Domain\Kioku\Capture\Store\EloquentCanonicalRawStore;
use App\Domain\Kioku\Embedding\EmbeddingGateway;
use App\Domain\Kioku\Embedding\FakeEmbeddingGateway;
use App\Domain\Kioku\Embedding\NullEmbeddingGateway;
use App\Domain\Kioku\Embedding\OpenAiEmbeddingGateway;
use App\Domain\Kioku\Embedding\Store\MysqlJsonVectorStore;
use App\Domain\Kioku\Embedding\VectorStore;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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

        $this->app->singleton(CaptureAdapterRegistry::class, fn (): CaptureAdapterRegistry => new CaptureAdapterRegistry([
            // More specific adapters first.
            new IosShortcutCaptureAdapter,
            new AudioFileImportCaptureAdapter,
            new BrowserVoiceCaptureAdapter,
            new WebTextCaptureAdapter,
        ]));

        $this->app->singleton(CanonicalRawStore::class, EloquentCanonicalRawStore::class);
        $this->app->singleton(MemoryProcessingPipeline::class, DefaultMemoryProcessingPipeline::class);
        $this->app->singleton(RawNormalizerRegistry::class, fn (): RawNormalizerRegistry => new RawNormalizerRegistry([
            new TextRawNormalizer,
            new AudioTranscriptionNormalizer,
            new UrlContentNormalizer,
        ]));

        $this->app->bind(EmbeddingGateway::class, function (Application $app): EmbeddingGateway {
            if (! config('kioku.embedding.enabled', false)) {
                return new NullEmbeddingGateway;
            }

            $provider = (string) config('kioku.embedding.provider', 'none');

            return match ($provider) {
                'none' => new NullEmbeddingGateway,
                'fake' => $app->make(FakeEmbeddingGateway::class),
                'openai' => $app->make(OpenAiEmbeddingGateway::class),
                default => throw new RuntimeException(
                    "Unknown embedding provider [{$provider}] (KIOKU_EMBEDDING_PROVIDER)."
                ),
            };
        });

        $this->app->singleton(FakeEmbeddingGateway::class);
        $this->app->singleton(VectorStore::class, MysqlJsonVectorStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Sign in with Apple is a community Socialite provider (docs/product/ios-app.md).
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('apple', AppleProvider::class);
        });

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
        //
        // 検出しても例外にはせずログに残す。ローカルで本番同等の画面確認を
        // する際（docs/dev/environments.md）、未修正の N+1 で画面ごと 500 に
        // ならないようにするため。storage/logs/laravel.log で確認する。
        $strictModels = app()->environment('local');

        Model::preventLazyLoading($strictModels);
        Model::preventSilentlyDiscardingAttributes($strictModels);
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('Lazy loading detected (N+1)', ['model' => $model::class, 'relation' => $relation]);
        });
        Model::handleDiscardedAttributeViolationUsing(function (Model $model, array $keys): void {
            Log::warning('Discarded non-fillable attributes', ['model' => $model::class, 'keys' => $keys]);
        });

        // パスワードポリシーは全環境共通（docs/dev/environments.md）
        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised(),
        );
    }
}
