<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboardService): View
    {
        return view('dashboard', $dashboardService->getDashboardData($request->user()));
    }
}
