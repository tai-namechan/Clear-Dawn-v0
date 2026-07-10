<?php

namespace App\Http\Controllers\Kioku;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * キオクの入口（MVP は準備中プレースホルダ）。
     */
    public function index(): Response
    {
        return Inertia::render('Kioku/ComingSoon');
    }
}
