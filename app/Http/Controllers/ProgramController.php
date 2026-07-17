<?php

namespace App\Http\Controllers;

use App\Http\Requests\Programs\ReviseProgramVersionRequest;
use App\Http\Resources\ProgramDetailResource;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Queries\GetProgramDetailQuery;
use App\Queries\GetProgramRoadmapQuery;
use App\Queries\GetProgramsQuery;
use App\Services\ReviseProgramVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(Request $request, GetProgramsQuery $query): Response
    {
        $programs = $query->handle($request->user());

        return Inertia::render('Programs/Index', [
            'programs' => ProgramResource::collection($programs)->resolve(),
        ]);
    }

    public function show(Request $request, Program $program, GetProgramDetailQuery $query): Response
    {
        Gate::authorize('view', $program);

        $loaded = $query->handle($request->user(), $program->id);

        return Inertia::render('Programs/Show', [
            'program' => ProgramDetailResource::make($loaded)->resolve(),
        ]);
    }

    public function roadmap(Request $request, Program $program, GetProgramRoadmapQuery $query): Response
    {
        Gate::authorize('view', $program);

        return Inertia::render('Programs/Roadmap', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
            ],
            'roadmap' => $query->handle($request->user(), $program),
        ]);
    }

    public function revise(
        ReviseProgramVersionRequest $request,
        Program $program,
        ReviseProgramVersionService $service,
    ): JsonResponse {
        Gate::authorize('update', $program);

        $version = $service->handle($program, $request->validated());

        return response()->json([
            'version' => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'status' => $version->status->value,
                'starts_on' => $version->starts_on->toDateString(),
                'ends_on' => $version->ends_on->toDateString(),
                'change_summary' => $version->change_summary,
                'change_reason' => $version->change_reason,
            ],
        ], 201);
    }
}
