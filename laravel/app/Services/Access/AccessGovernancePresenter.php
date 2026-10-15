<?php

namespace App\Services\Access;

use App\Models\AccessApprovalRequest;
use App\Models\AccessAuditTimelineEvent;
use App\Models\AccessRiskIncident;
use App\Models\AuthorizationDocument;
use App\Models\User;
use App\Services\AccessVisibilityService;

/**
 * The props of the Access Governance & Risk Center (Dashboard/AccessGovernance):
 * recent risk incidents, approval requests, authorization documents, the
 * audit timeline, the visibility matrix and the summary badge counts.
 */
class AccessGovernancePresenter
{
    public function __construct(private readonly AccessVisibilityService $visibility) {}

    /**
     * @return array<string, mixed>
     */
    public function props(?User $viewer): array
    {
        return [
            'incidents' => AccessRiskIncident::query()->with(['user', 'resolver'])->latest('occurred_at')->limit(50)->get(),
            'approvalRequests' => AccessApprovalRequest::query()->with(['homeowner', 'gatePass'])->latest()->get(),
            'documents' => AuthorizationDocument::query()->with(['gatePass', 'user'])->latest()->get(),
            'recentTimeline' => AccessAuditTimelineEvent::query()->latest('occurred_at')->limit(40)->get(),
            'visibilityMatrix' => $this->visibility->getVisibilityMatrix(),
            'userScope' => $viewer ? [
                'role' => $this->visibility->resolveUserRole($viewer),
                'associatedProperties' => $this->visibility->getUserAssociatedProperties($viewer),
            ] : null,
            'metrics' => $this->metrics(),
        ];
    }

    /**
     * @return array{activeIncidents: int, criticalIncidents: int, pendingApprovals: int, expiringDocuments: int, expiredPassesToday: int}
     */
    private function metrics(): array
    {
        $open = fn () => AccessRiskIncident::where('status', 'NEW');

        return [
            'activeIncidents' => $open()->count(),
            'criticalIncidents' => $open()->where('risk_level', 'CRITICAL')->count(),
            'pendingApprovals' => AccessApprovalRequest::where('status', 'pending')->count(),
            'expiringDocuments' => AuthorizationDocument::whereIn('status', ['expiring_soon', 'expired'])->count(),
            'expiredPassesToday' => AccessAuditTimelineEvent::where('event_type', 'CREDENTIAL_DENIED')
                ->where('headline', 'Policy Window Expired')
                ->whereDate('occurred_at', today())
                ->count(),
        ];
    }
}
