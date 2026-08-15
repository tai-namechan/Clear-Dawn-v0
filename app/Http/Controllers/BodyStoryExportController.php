<?php

namespace App\Http\Controllers;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Enums\BodyStoryKind;
use App\Http\Requests\BodyStory\ShowBodyStoryExportRequest;
use App\Models\User;
use App\Queries\GetBodyStoryExportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Story画像用データの返却。
 */
class BodyStoryExportController extends Controller
{
    /**
     * 指定日・種類の Story 表示データを返す。
     */
    public function show(
        ShowBodyStoryExportRequest $request,
        GetBodyStoryExportQuery $query,
        UserTimezoneResolver $timezoneResolver,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $kind = BodyStoryKind::from((string) $request->validated('kind'));
        $date = Carbon::parse(
            $request->validated('date') ?? $timezoneResolver->todayDateString($user),
        );

        return response()->json($query->handle($user, $date, $kind));
    }
}
