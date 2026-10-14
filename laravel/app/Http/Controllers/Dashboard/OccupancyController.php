<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Occupancy\ResolveMusterRequest;
use App\Http\Requests\Occupancy\StartMusterRequest;
use App\Http\Requests\Occupancy\UpdateMusterStatusRequest;
use App\Models\MusterSession;
use App\Services\EmergencyMusterService;
use App\Services\Occupancy\OccupancyCategoryResolver;
use App\Services\Occupancy\OccupancyCensusQueryService;
use App\Services\Occupancy\OccupancyHierarchyBuilder;
use App\Services\Occupancy\OccupancyPagePresenter;
use App\Support\Csv;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who is on the estate right now, by unit, and the emergency muster roll.
 *
 * Security, administrators and staff see everything. Anyone else sees the
 * estate-wide totals and their own unit only: the per-unit picture is a list
 * of which homes are empty, and the roll carries every occupant's contact and
 * vehicle. unit() and both exports had no check at all, so any signed-in
 * account could ask who was inside any unit or download the whole roster.
 */
class OccupancyController extends Controller
{
    private const ROSTER_HEADER = ['Unit', 'Name', 'Category', 'Role', 'CheckIn_Time', 'Gate', 'Host_Resident', 'Vehicle', 'Contact', 'Pass_ID'];

    public function __construct(
        private readonly OccupancyCategoryResolver $categories,
        private readonly OccupancyCensusQueryService $census,
        private readonly OccupancyHierarchyBuilder $hierarchy,
        private readonly EmergencyMusterService $musterService,
    ) {}

    /** "Who's on property?": see OccupancyPagePresenter for what each viewer sees. */
    public function index(Request $request, OccupancyPagePresenter $presenter): InertiaResponse
    {
        $filter = fn (string $key) => $request->string($key)->trim()->toString();

        return Inertia::render('Dashboard/Occupancy', $presenter->props($request->user(), [
            'category' => $filter('category'),
            'search' => $filter('search'),
            'tab' => $filter('tab'),
            'unit' => $filter('unit'),
            'page' => (int) $request->input('page', 1),
        ]));
    }

    /** "Who's inside Unit 14?": occupancy managers, or that unit's resident. */
    public function unit(string $unit): JsonResponse
    {
        $this->authorize('viewUnitOccupancy', [$unit]);

        $drilldown = $this->hierarchy->getUnitDrilldown($unit);

        return response()->json([
            'success' => true,
            ...collect($drilldown)->only([
                'unit', 'total', 'totalCurrentlyInside', 'totalExpectedToday', 'summary',
                'expectedSummary', 'occupants', 'expected', 'rosterByCategory',
            ])->all(),
            'ownership' => $drilldown['ownership'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * The estate's spatial tree: whole for occupancy managers, the caller's
     * own unit for anyone else, and nothing without one (a null scope is the
     * whole estate to the builder).
     */
    public function hierarchy(Request $request): JsonResponse
    {
        $user = $request->user();
        $manages = $user->can('manageOccupancy');
        $ownUnit = $this->categories->normalizeUnit($user->lot);

        return response()->json([
            'success' => true,
            'hierarchy' => $manages || $ownUnit !== null
                ? $this->hierarchy->getCommunityHierarchy($manages ? null : $ownUnit)
                : [],
        ]);
    }

    /** Start an emergency muster (evacuation roll call). */
    public function startMuster(StartMusterRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        try {
            $session = $this->musterService->startSession(
                incidentType: $validated['incident_type'],
                title: $validated['title'],
                assemblyPoint: $validated['assembly_point'] ?? null,
                notes: $validated['notes'] ?? null,
                initiator: $request->user(),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['muster' => $e->getMessage()])->status(409);
        }

        $message = "Emergency Muster session for {$session->incidentLabel()} initiated.";

        return $this->reply($request, "{$message} Live roll-call active.", ['message' => $message, 'session_id' => $session->id]);
    }

    /** Mark one person safe, missing, evacuated, needing assistance or checked out. */
    public function updateMusterStatus(UpdateMusterStatusRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $session = MusterSession::findOrFail($validated['session_id']);

        try {
            $record = $this->musterService->updateStatus(
                session: $session,
                occupantId: $validated['occupant_id'],
                status: $validated['status'],
                notes: $validated['notes'] ?? null,
                marker: $request->user(),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['occupant_id' => $e->getMessage()]);
        }

        return $this->reply($request, "Occupant marked {$record->statusLabel()}.", [
            'status' => $record->status,
            'status_label' => $record->statusLabel(),
            'counts' => $session->fresh()->getCounts(),
        ]);
    }

    /** Close the muster and archive its roll call. */
    public function resolveMuster(ResolveMusterRequest $request): RedirectResponse|JsonResponse
    {
        $this->musterService->resolveSession(
            session: MusterSession::findOrFail($request->validated('session_id')),
            resolver: $request->user(),
            notes: $request->validated('resolution_notes') ?? 'All muster points cleared and verified.',
        );

        $message = 'Emergency muster session concluded and archived.';

        return $this->reply($request, $message, ['message' => $message]);
    }

    /** The running (or else the latest) muster's roll call as CSV, for first responders. */
    public function exportMusterRoster(Request $request): Response
    {
        $this->authorize('manageOccupancy');

        $session = $this->musterService->getActiveSession() ?? MusterSession::latest('id')->first();

        if (! $session) {
            return $this->exportRoster();
        }

        $filename = sprintf('Emergency_Muster_Roster_%s_%s.csv', $session->incident_type, now()->format('Y-m-d_Hi'));

        return response($this->musterService->exportCsv($session), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /** Everyone on the estate now, as CSV for first responders and muster marshals. */
    public function exportRoster(): Response
    {
        $this->authorize('manageOccupancy');

        $rows = $this->census->getAllCurrentOccupants()->map(fn (array $item) => [
            $item['unit'], $item['name'], $item['categoryLabel'], $item['role'], $item['checkedInTime'],
            $item['gate'], $item['host'] ?? 'N/A', $item['vehicle'] ?? 'N/A', $item['contact'] ?? 'N/A', $item['passId'],
        ]);

        return Csv::download(sprintf('Emergency_Evacuation_Roster_%s.csv', now()->format('Y-m-d_Hi')), self::ROSTER_HEADER, $rows);
    }

    /**
     * JSON for the live roll-call screen, a flash message for a form post.
     *
     * @param  array<string, mixed>  $json
     */
    private function reply(Request $request, string $flash, array $json): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(['success' => true, ...$json])
            : back()->with('success', $flash);
    }
}
