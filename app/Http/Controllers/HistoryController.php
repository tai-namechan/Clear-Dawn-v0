<?php

namespace App\Http\Controllers;

use App\Enums\ActivityLogEventType;
use App\Http\Resources\ActivityLogResource;
use App\Queries\GetActivityHistoryQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request, GetActivityHistoryQuery $query): Response
    {
        $filters = [];

        if ($request->filled('event_type')) {
            $filters['event_type'] = ActivityLogEventType::from($request->string('event_type')->toString());
        }

        if ($request->filled('from')) {
            $filters['from'] = $request->string('from')->toString();
        }

        if ($request->filled('to')) {
            $filters['to'] = $request->string('to')->toString();
        }

        $history = $query->handle($request->user(), $filters);

        return Inertia::render('History/Index', [
            'history' => ActivityLogResource::collection($history)->response()->getData(true),
            'filters' => [
                'event_type' => $request->input('event_type'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        ]);
    }
}
