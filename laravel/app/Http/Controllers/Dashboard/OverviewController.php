<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Reporting\DashboardOverviewPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard home. What each tile holds, and for whom, is in
 * DashboardOverviewPresenter.
 */
class OverviewController extends Controller
{
    public function __invoke(Request $request, DashboardOverviewPresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Overview', $presenter->props($request->user()));
    }
}
