<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $generationId = (string) Str::ulid();

            try {
                $created = YoyuBriefing::query()->create([
                    'user_id' => $user->id,
                    'date' => $briefingDate,
                    'body' => '朝ブリーフィングを生成しています…',
                    'structured_data' => null,
                    'status' => 'pending',
                    'generation_id' => $generationId,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                return $this->findTodayOrFail($user->id, $briefingDate, $e);
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                return $this->findTodayOrFail($user->id, $briefingDate, $e);
            }

            GenerateYoyuBriefingJob::dispatch(
                $created->id,
                $briefingDate,
                $timezone,
                $generationId,
            )->afterCommit();
            $dispatched = true;

            return $created;
        });

        return [
            'briefing' => $briefing,
            'dispatched' => $dispatched,
        ];
    }

    /**
     * Manual regenerate: keep old body/structured_data.
     * Does not dispatch a second active job while pending/generating.
     *
     * @return array{briefing: YoyuBriefing, dispatched: bool, already_running: bool}
     */
    public function regenerate(User $user): array
    {
        $timezone = $this->timezones->for($user);
        $briefingDate = CarbonImmutable::now($timezone)->toDateString();
        $dispatched = false;
        $alreadyRunning = false;

        $briefing = DB::transaction(function () use ($user, $briefingDate, $timezone, &$dispatched, &$alreadyRunning): YoyuBriefing {
            $existing = YoyuBriefing::query()
                ->where('user_id', $user->id)
                ->whereDate('date', $briefingDate)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, ['pending', 'generating'], true)) {
                $alreadyRunning = true;

                return $existing;
            }

            $generationId = (string) Str::ulid();

            if ($existing !== null) {
                $existing->update([
                    'body' => $existing->body !== ''
                        ? $existing->body
                        : '朝ブリーフィングを生成しています…',
                    'status' => 'generating',
                    'generation_id' => $generationId,
                ]);
                $briefing = $existing->fresh() ?? $existing;
            } else {
                try {
                    $briefing = YoyuBriefing::query()->create([
                        'user_id' => $user->id,
                        'date' => $briefingDate,
                        'body' => '朝ブリーフィングを生成しています…',
                        'structured_data' => null,
                        'status' => 'generating',
                        'generation_id' => $generationId,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $race = $this->findTodayOrFail($user->id, $briefingDate, $e);
                    if (in_array($race->status, ['pending', 'generating'], true)) {
                        $alreadyRunning = true;

                        return $race;
                    }

                    $generationId = (string) Str::ulid();
                    $race->update([
                        'body' => $race->body !== '' ? $race->body : '朝ブリーフィングを生成しています…',
                        'status' => 'generating',
                        'generation_id' => $generationId,
                    ]);
                    $briefing = $race->fresh() ?? $race;
                } catch (QueryException $e) {
                    if (! $this->isUniqueViolation($e)) {
                        throw $e;
                    }

                    $race = $this->findTodayOrFail($user->id, $briefingDate, $e);
                    if (in_array($race->status, ['pending', 'generating'], true)) {
                        $alreadyRunning = true;

                        return $race;
                    }

                    $generationId = (string) Str::ulid();
                    $race->update([
                        'body' => $race->body !== '' ? $race->body : '朝ブリーフィングを生成しています…',
                        'status' => 'generating',
                        'generation_id' => $generationId,
                    ]);
                    $briefing = $race->fresh() ?? $race;
                }
            }

            if (! $alreadyRunning) {
                GenerateYoyuBriefingJob::dispatch(
                    $briefing->id,
                    $briefingDate,
                    $timezone,
                    (string) $briefing->generation_id,
                )->afterCommit();
                $dispatched = true;
            }

            return $briefing;
        });

        return [
            'briefing' => $briefing,
            'dispatched' => $dispatched,
            'already_running' => $alreadyRunning,
        ];
    }

    private function findTodayOrFail(int $userId, string $briefingDate, \Throwable $previous): YoyuBriefing
    {
        $race = YoyuBriefing::query()
            ->where('user_id', $userId)
            ->whereDate('date', $briefingDate)
            ->first();

        if ($race !== null) {
            return $race;
        }

        throw $previous;
    }

    private function isUniqueViolation(UniqueConstraintViolationException|QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || (string) $e->getCode() === '23000';
    }
}
