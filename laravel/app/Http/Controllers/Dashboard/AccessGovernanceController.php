<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AccessApprovalRequest;
use App\Models\AccessAuditTimelineEvent;
use App\Models\AccessRiskIncident;
use App\Models\AuthorizationDocument;
use App\Models\User;
use App\Services\AccessApprovalWorkflowService;
use App\Services\AccessAuditTimelineService;
use App\Services\AccessExpirationPolicyService;
use App\Services\AccessRiskEngine;
use App\Services\AccessVisibilityService;
use App\Services\DigitalAuthorizationDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccessGovernanceController extends Controller
{
    public function __construct(
        private readonly AccessRiskEngine $riskEngine,
        private readonly AccessApprovalWorkflowService $approvalWorkflowService,
        private readonly AccessExpirationPolicyService $expirationPolicyService,
        private readonly DigitalAuthorizationDocumentService $documentService,
        private readonly AccessAuditTimelineService $timelineService,
        private readonly AccessVisibilityService $visibilityService
    ) {}

    /**
     * Render the unified Access Governance & Risk Center.
     */
    public function index(Request $request): Response
    {
        // Auto-seed Mary Smith timeline demo if not present
        if (AccessAuditTimelineEvent::where('pass_id', 'GP-MARY-1004')->count() === 0) {
            $this->timelineService->seedExampleMarySmithTimeline();
        }

        // Auto-seed some realistic demo documents & requests if completely empty
        $this->ensureDemoDataExists($request->user());

        $incidents = AccessRiskIncident::query()
            ->with(['user', 'resolver'])
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $approvalRequests = AccessApprovalRequest::query()
            ->with(['homeowner', 'gatePass'])
            ->latest()
            ->get();

        $documents = AuthorizationDocument::query()
            ->with(['gatePass', 'user'])
            ->latest()
            ->get();

        $recentTimeline = AccessAuditTimelineEvent::query()
            ->latest('occurred_at')
            ->limit(40)
            ->get();

        // Metrics for summary badges
        $activeIncidentsCount = AccessRiskIncident::where('status', 'NEW')->count();
        $criticalIncidentsCount = AccessRiskIncident::where('status', 'NEW')->where('risk_level', 'CRITICAL')->count();
        $pendingApprovalsCount = AccessApprovalRequest::where('status', 'pending')->count();
        $expiringDocsCount = AuthorizationDocument::whereIn('status', ['expiring_soon', 'expired'])->count();
        $expiredPassesTodayCount = AccessAuditTimelineEvent::where('event_type', 'CREDENTIAL_DENIED')
            ->where('headline', 'Policy Window Expired')
            ->whereDate('occurred_at', today())
            ->count();

        $userScope = $request->user() ? [
            'role' => $this->visibilityService->resolveUserRole($request->user()),
            'associatedProperties' => $this->visibilityService->getUserAssociatedProperties($request->user()),
        ] : null;

        return Inertia::render('Dashboard/AccessGovernance', [
            'incidents' => $incidents,
            'approvalRequests' => $approvalRequests,
            'documents' => $documents,
            'recentTimeline' => $recentTimeline,
            'visibilityMatrix' => $this->visibilityService->getVisibilityMatrix(),
            'userScope' => $userScope,
            'metrics' => [
                'activeIncidents' => $activeIncidentsCount,
                'criticalIncidents' => $criticalIncidentsCount,
                'pendingApprovals' => $pendingApprovalsCount,
                'expiringDocuments' => $expiringDocsCount,
                'expiredPassesToday' => $expiredPassesTodayCount,
            ],
        ]);
    }

    /**
     * Approve the current stage of an access approval request.
     */
    public function approveRequest(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->approvalWorkflowService->approveStage($id, $request->user(), $validated['notes'] ?? null);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['request' => $e->getMessage()]);
        }

        return back()->with('success', 'Access approval workflow stage successfully confirmed.');
    }

    /**
     * Reject an access approval request.
     */
    public function rejectRequest(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->approvalWorkflowService->rejectRequest($id, $request->user(), $validated['reason']);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['request' => $e->getMessage()]);
        }

        return back()->with('success', 'Access authorization request rejected and logged in forensic timeline.');
    }

    /**
     * Emergency lockdown of a suspicious credential.
     */
    public function lockdownIncident(Request $request, int $id): RedirectResponse
    {
        $incident = AccessRiskIncident::findOrFail($id);
        $reason = $request->input('reason', 'Security emergency: Immediate lockdown from Access Risk Engine');

        if ($incident->pass_id) {
            $this->riskEngine->lockdownCredential($incident->pass_id, $request->user(), $reason);
        } else {
            $incident->update([
                'status' => 'LOCKED_DOWN',
                'resolved_by' => $request->user()->id,
                'resolution_notes' => $reason,
            ]);
        }

        return back()->with('success', 'Credential immediately suspended and locked out across all physical gates.');
    }

    /**
     * Resolve a suspicious incident.
     */
    public function resolveIncident(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:500'],
        ]);

        $incident = AccessRiskIncident::findOrFail($id);
        $incident->update([
            'status' => 'RESOLVED',
            'resolved_by' => $request->user()->id,
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        return back()->with('success', 'Incident resolved.');
    }

    /**
     * Fetch timeline for a specific pass.
     */
    public function getPassTimeline(string $passId): JsonResponse
    {
        $events = $this->timelineService->getPassTimeline($passId);

        return response()->json($events);
    }

    /**
     * Execute mandatory expiration sweep across all passes.
     */
    public function sweepExpiredPasses(): RedirectResponse
    {
        $count = $this->expirationPolicyService->sweepExpiredPasses();

        return back()->with('success', "Mandatory expiration policy sweep executed: {$count} stale authorizations revoked.");
    }

    /**
     * Audit digital lease and authorization documents.
     */
    public function auditDocuments(): RedirectResponse
    {
        $result = $this->documentService->auditDocumentExpirations();

        return back()->with('success', "Compliance audit complete: {$result['expired_documents_found']} expired documents found, {$result['suspended_passes_count']} passes suspended.");
    }

    /**
     * Attach a new digital compliance document.
     */
    public function attachDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:lease,authorization_letter,id_verification,insurance,contractor_certificate,other'],
            'holder_name' => ['required', 'string', 'max:255'],
            'expires_at' => ['required', 'date'],
            'gate_pass_id' => ['nullable', 'integer', 'exists:gate_passes,id'],
        ]);

        $this->documentService->attachDocument($validated, $request->user());

        return back()->with('success', 'Digital compliance document attached and verified.');
    }

    /**
     * Seed baseline demo entries if empty to showcase enterprise visual standards immediately.
     */
    private function ensureDemoDataExists(?User $user): void
    {
        if (AccessApprovalRequest::count() === 0 && $user) {
            // 1. Long-term occupant request
            $this->approvalWorkflowService->submitRequest([
                'category' => 'long_term_occupant',
                'applicant_name' => 'Marcus Vance',
                'applicant_email' => 'marcus.vance@example.com',
                'applicant_phone' => '+1 (876) 555-0192',
                'property_number' => 'Villa 42B',
                'lease_expires_at' => now()->addMonths(11)->format('Y-m-d'),
                'notes' => '12-month residential lease endorsement',
            ], $user);

            // 2. Contractor request
            $this->approvalWorkflowService->submitRequest([
                'category' => 'contractor',
                'applicant_name' => 'Apex Solar Innovations (Carlos Ruiz)',
                'applicant_email' => 'carlos@apexsolar.com',
                'applicant_phone' => '+1 (876) 555-0288',
                'property_number' => 'Lot 19',
                'notes' => 'Solar battery installation and commercial inverter maintenance',
            ], $user);

            // 3. Visitor request (instant)
            $this->approvalWorkflowService->submitRequest([
                'category' => 'visitor',
                'applicant_name' => 'Sophia Campbell',
                'applicant_email' => 'sophia.c@example.com',
                'applicant_phone' => '+1 (876) 555-0371',
                'property_number' => 'Unit 14',
                'notes' => 'Dinner guest, arrival estimated 19:30',
            ], $user);
        }

        if (AuthorizationDocument::count() === 0) {
            $this->documentService->attachDocument([
                'title' => 'Residential Lease Agreement (Marcus Vance)',
                'document_type' => 'lease',
                'holder_name' => 'Marcus Vance',
                'expires_at' => now()->addMonths(11)->toIso8601String(),
                'status' => 'valid',
            ], $user);

            $this->documentService->attachDocument([
                'title' => 'Commercial Contractor Liability Insurance (Apex Solar)',
                'document_type' => 'insurance',
                'holder_name' => 'Apex Solar Innovations',
                'expires_at' => now()->addDays(8)->toIso8601String(), // expiring soon
                'status' => 'expiring_soon',
            ], $user);

            $this->documentService->attachDocument([
                'title' => 'Electrical Contractor License #EL-8841',
                'document_type' => 'contractor_certificate',
                'holder_name' => 'Apex Solar Innovations',
                'expires_at' => now()->subDays(3)->toIso8601String(), // expired
                'status' => 'expired',
            ], $user);
        }

        if (AccessRiskIncident::count() === 0) {
            AccessRiskIncident::create([
                'pass_id' => 'GP-VIS-LEAK-9921',
                'gate' => 'MAIN_GATE',
                'flag_type' => 'REVOKED_ATTEMPT',
                'risk_level' => 'CRITICAL',
                'title' => '⚠️ Suspicious Credential Activity: Revoked QR Presented',
                'description' => 'Unauthorized entry attempt using revoked pass #GP-VIS-LEAK-9921 at Main Vehicle Barrier Gate. Decommissioned screenshot or cloned credential presented.',
                'evidence' => [
                    'pass_id' => 'GP-VIS-LEAK-9921',
                    'holder_name' => 'Unknown Presentation',
                    'revoked_at' => now()->subHours(4)->toIso8601String(),
                    'revocation_reason' => 'Reported Lost/Stolen by Resident',
                    'gate' => 'MAIN_GATE',
                    'scan_decision' => 'REJECT',
                    'scanned_by' => 'Guard Officer Davis',
                ],
                'status' => 'NEW',
                'occurred_at' => now()->subMinutes(18),
            ]);

            AccessRiskIncident::create([
                'pass_id' => 'GP-CON-APEX-4102',
                'gate' => 'SERVICE_NORTH',
                'flag_type' => 'SIMULTANEOUS_MULTI_GATE',
                'risk_level' => 'CRITICAL',
                'title' => '🚨 Credential Cloning Detected: Simultaneous Multi-Gate Presentation',
                'description' => 'Pass #GP-CON-APEX-4102 was presented at Service North Gate only 42 seconds after presentation at Main Gate (impossible transit). Active credential sharing detected.',
                'evidence' => [
                    'primary_gate' => 'MAIN_GATE',
                    'secondary_gate' => 'SERVICE_NORTH',
                    'seconds_delta' => 42,
                    'first_scan_at' => now()->subMinutes(35)->subSeconds(42)->toIso8601String(),
                    'second_scan_at' => now()->subMinutes(35)->toIso8601String(),
                ],
                'status' => 'NEW',
                'occurred_at' => now()->subMinutes(35),
            ]);
        }
    }
}
