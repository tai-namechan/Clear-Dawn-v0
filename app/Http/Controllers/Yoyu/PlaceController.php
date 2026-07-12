<?php

namespace App\Http\Controllers\Yoyu;

use App\Domain\Yoyu\Services\YoyuEventTravelLeadService;
use App\Domain\Yoyu\Services\YoyuPlaceTravelService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Yoyu\UpdateYoyuEventTravelLeadRequest;
use App\Http\Requests\Yoyu\UpsertYoyuPlaceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PlaceController extends Controller
{
    public function upsert(UpsertYoyuPlaceRequest $request, YoyuPlaceTravelService $places): RedirectResponse
    {
        $data = $request->validated();

        $places->upsert(
            (int) $request->user()->id,
            $data['name'],
            (int) $data['travel_minutes'],
            isset($data['external_id']) ? (string) $data['external_id'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '移動時間を登録しました。']);

        return redirect()->route('yoyu.home', ['tab' => 'today']);
    }

    public function updateEventTravelLead(
        UpdateYoyuEventTravelLeadRequest $request,
        YoyuEventTravelLeadService $travelLead,
    ): RedirectResponse {
        $data = $request->validated();
        $clear = $request->boolean('clear');

        $result = $travelLead->upsert(
            (int) $request->user()->id,
            (string) $data['external_id'],
            $clear ? null : (int) $data['prep_minutes'],
            $clear ? null : (int) $data['buffer_minutes'],
            $clear,
        );

        if (! $result['updated']) {
            Inertia::flash('toast', ['type' => 'error', 'message' => '予定が見つかりませんでした。']);

            return redirect()->route('yoyu.home', ['tab' => 'today']);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $clear
                ? '支度・余白を既定値に戻しました。'
                : 'この予定の支度・余白を保存しました。',
        ]);

        return redirect()->route('yoyu.home', ['tab' => 'today']);
    }
}
