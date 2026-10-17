<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Incidents\AddIncidentUpdateAction;
use App\Actions\Incidents\IssueIncidentAllClearAction;
use App\Actions\Incidents\ReportCommunityIncidentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incidents\AddIncidentUpdateRequest;
use App\Http\Requests\Incidents\IssueIncidentAllClearRequest;
use App\Http\Requests\Incidents\StoreIncidentRequest;
use App\Models\CommunityIncident;
use App\Services\Incidents\IncidentPagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function index(Request $request, IncidentPagePresenter $presenter): Response
    {
        return Inertia::render('Dashboard/Incidents', $presenter->props($request->user()));
    }

    public function store(StoreIncidentRequest $request, ReportCommunityIncidentAction $action): RedirectResponse|JsonResponse
    {
        $incident = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Incident {$incident->incident_number} logged successfully.",
                'incident' => $incident,
            ], 201);
        }

        return redirect()->route('dashboard.incidents')->with('success', "Incident {$incident->incident_number} logged successfully.");
    }

    public function show(Request $request, CommunityIncident $incident, IncidentPagePresenter $presenter): Response|JsonResponse
    {
        $canManage = $request->user()->can('manageSecurity');
        $data = $presenter->transformIncident($incident, $canManage);

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Dashboard/IncidentDetails', [
            'incident' => $data,
            'can' => [
                'manage' => $canManage,
                'viewSensitiveDetails' => $canManage,
            ],
        ]);
    }

    public function addUpdate(AddIncidentUpdateRequest $request, CommunityIncident $incident, AddIncidentUpdateAction $action): RedirectResponse|JsonResponse
    {
        $update = $action->execute($incident, $request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Incident update posted.',
                'update' => $update,
            ]);
        }

        return back()->with('success', 'Incident update posted.');
    }

    public function issueAllClear(IssueIncidentAllClearRequest $request, CommunityIncident $incident, IssueIncidentAllClearAction $action): RedirectResponse|JsonResponse
    {
        $action->execute($incident, $request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "All-Clear broadcast issued for incident {$incident->incident_number}.",
            ]);
        }

        return back()->with('success', "All-Clear broadcast issued for incident {$incident->incident_number}.");
    }

    public function close(Request $request, CommunityIncident $incident): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->can('manageSecurity'), 403);

        $incident->update(['status' => CommunityIncident::STATUS_CLOSED]);
        $request->user()->recordActivity("Closed incident {$incident->incident_number}");

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Incident {$incident->incident_number} closed."]);
        }

        return back()->with('success', "Incident {$incident->incident_number} closed.");
    }
}
