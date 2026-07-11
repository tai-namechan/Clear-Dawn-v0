<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\AI\AiUsageSummary;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiUsageController extends Controller
{
    public function edit(Request $request, AiUsageSummary $summary): Response
    {
        $user = $request->user();

        return Inertia::render('settings/AiUsage', [
            'usage' => $summary->forUser((int) $user->id),
        ]);
    }
}
