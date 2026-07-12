<?php

namespace App\Http\Controllers\Yoyu;

use App\Domain\Yoyu\Services\YoyuPlaceTravelService;
use App\Http\Controllers\Controller;
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
}
