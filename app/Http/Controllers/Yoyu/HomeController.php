<?php

namespace App\Http\Controllers\Yoyu;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * ヨユウの入口（MVP は準備中プレースホルダ）。
     */
    public function index(): Response
    {
        return Inertia::render('Yoyu/ComingSoon');
    }
}
