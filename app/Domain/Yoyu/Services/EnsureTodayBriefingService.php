<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Ensures today's briefing row exists and dispatches generation once (after commit).
 */
final class EnsureTodayBriefingService
{
    public function __construct(
        private UserTimezoneResolver $timezones,
    ) {}

    /**
     * @return array{briefing: YoyuBriefing, dispatched: bool}
     */
    public function ensure(User $user): array
    {
        $timezone = $this->timezones->for($user);
        $briefingDate = CarbonImmutable::now($timezone)->toDateString();
        $dispatched = false;

        $briefing = DB::transaction(function () use ($user, $briefingDate, $timezone, &$dispatched): YoyuBriefing {
            $existing = YoyuBriefing::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $briefingDate)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            try {
                $created = YoyuBriefing::query()->create([
                    'user_id' => $user->id,
                    'date' => $briefingDate,
                    'body' => '朝ブリーフィングを生成しています…',
                    'structured_data' => null,
                    'status' => 'pending',
                ]);
            } catch (QueryException $e) {
                // Concurrent first access hit unique(user_id, date).
                $race = YoyuBriefing::query()
                    ->where('user_id', $user->id)
                    ->whereDate('date', $briefingDate)
                    ->first();

                if ($race !== null) {
                    return $race;
                }

                throw $e;
            }

            GenerateYoyuBriefingJob::dispatch($created->id, $briefingDate, $timezone)
                ->afterCommit();
            $dispatched = true;

            return $created;
        });

        return [
            'briefing' => $briefing,
            'dispatched' => $dispatched,
        ];
    }

    /**
     * Manual regenerate: keep old body/structured_data, mark generating, dispatch.
     */
    public function regenerate(User $user): YoyuBriefing
    {
        $timezone = $this->timezones->for($user);
        $briefingDate = CarbonImmutable::now($timezone)->toDateString();

        return DB::transaction(function () use ($user, $briefingDate, $timezone): YoyuBriefing {
            $existing = YoyuBriefing::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $briefingDate)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'body' => $existing->body !== ''
                        ? $existing->body
                        : '朝ブリーフィングを生成しています…',
                    // Never null out structured_data on regenerate start.
                    'status' => 'generating',
                ]);
                $briefing = $existing->fresh() ?? $existing;
            } else {
                $briefing = YoyuBriefing::query()->create([
                    'user_id' => $user->id,
                    'date' => $briefingDate,
                    'body' => '朝ブリーフィングを生成しています…',
                    'structured_data' => null,
                    'status' => 'generating',
                ]);
            }

            GenerateYoyuBriefingJob::dispatch($briefing->id, $briefingDate, $timezone)
                ->afterCommit();

            return $briefing;
        });
    }
}
