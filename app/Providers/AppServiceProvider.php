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
