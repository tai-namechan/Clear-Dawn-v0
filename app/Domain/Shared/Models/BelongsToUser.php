<?php

namespace App\Domain\Shared\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Forces user_id scoping on domain models (Kioku / Yoyu).
 *
 * @method static Builder<static> withoutUserScope()
 */
trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $builder): void {
            $userId = Auth::id();

            if ($userId !== null) {
                $builder->where($builder->getModel()->getTable().'.user_id', $userId);
            }

            // 認証コンテキストが無い場合（キューワーカー / artisan / スケジューラ）は
            // 現状スコープを適用しない = フェイルオープンである。
            //
            // これは設計上の欠陥（docs/audit/2026-07-26-pre-release-audit.md H-4）だが、
            // ここで whereRaw('1 = 0') によるフェイルクローズへ切り替えると、
            // 現在ジョブ／コマンドから到達する以下の呼び出しが無言で壊れる。
            //
            //   - KiokuConciergePilotService:84
            //     KiokuConciergeSchedule::query()->updateOrCreate(['user_id' => ...])
            //     → 既存行が引けず、実行のたびにスケジュールを重複作成する
            //   - CachedGoogleCalendarProvider:87（GenerateYoyuBriefingJob から到達）
            //     → キャッシュ済みカレンダー予定が 0 件になり、ブリーフィングから
            //       予定が黙って消える
            //   - KiokuLetterGenerator の dedupe 再取得経路
            //
            // 先に上記の呼び出し側へ withoutUserScope() と明示的な user_id 条件を
            // 入れ切ってから切り替える。順序を逆にすると、防ごうとしている事故より
            // 大きな障害を作ることになる。対応は監査ロードマップ Phase 6。
        });

        static::creating(function ($model): void {
            if ($model->user_id === null && Auth::id() !== null) {
                $model->user_id = Auth::id();
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutUserScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('user');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
