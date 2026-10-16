<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Maintenance\ReportMaintenanceIssueAction;
use App\Actions\Maintenance\UpdateMaintenanceIssueAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceIssueRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceIssueRequest;
use App\Models\MaintenanceIssue;
use App\Services\Maintenance\MaintenancePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maintenance and issue reports. Who sees what is MaintenanceIssuePolicy:
 * residents their own reports, those who triage every report.
 */
class MaintenanceController extends Controller
{
    public function index(Request $request, MaintenancePresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Maintenance', $presenter->props(
            $request->user(),
            $request->only('category', 'status', 'priority', 'search'),
        ));
    }

    public function store(StoreMaintenanceIssueRequest $request, ReportMaintenanceIssueAction $report): RedirectResponse
    {
        $issue = $report->execute($request->user(), $request->validated(), $request->file('photos'));

        return back()->with('success', "Report {$issue->reference_number} submitted successfully. Our maintenance team has been notified.");
    }

    public function update(UpdateMaintenanceIssueRequest $request, MaintenanceIssue $maintenanceIssue, UpdateMaintenanceIssueAction $update): RedirectResponse
    {
        $update->execute($maintenanceIssue, $request->user(), $request->validated(), $request->file('photos'));

        return back()->with('success', "Issue {$maintenanceIssue->reference_number} updated.");
    }
}
