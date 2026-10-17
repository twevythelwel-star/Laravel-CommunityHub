<?php

namespace App\Services\Incidents;

use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;

class IncidentPagePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function props(User $viewer): array
    {
        $canManage = $viewer->can('manageSecurity');

        $incidents = CommunityIncident::with(['reporter', 'allClearUser', 'updates.author'])
            ->latest()
            ->get()
            ->map(fn (CommunityIncident $incident) => $this->transformIncident($incident, $canManage));

        $activeCount = CommunityIncident::active()->count();
        $criticalCount = CommunityIncident::active()
            ->whereIn('severity', [CommunityIncident::SEVERITY_HIGH, CommunityIncident::SEVERITY_CRITICAL])
            ->count();
        $allClearCount = CommunityIncident::where('status', CommunityIncident::STATUS_ALL_CLEAR)->count();

        return [
            'incidents' => $incidents,
            'stats' => [
                'total' => CommunityIncident::count(),
                'active' => $activeCount,
                'critical' => $criticalCount,
                'allClear' => $allClearCount,
            ],
            'can' => [
                'manage' => $canManage,
                'report' => $canManage,
                'viewSensitiveDetails' => $canManage,
            ],
            'categories' => [
                ['value' => CommunityIncident::CATEGORY_SECURITY, 'label' => 'Perimeter & Security'],
                ['value' => CommunityIncident::CATEGORY_FIRE, 'label' => 'Fire & Hazard'],
                ['value' => CommunityIncident::CATEGORY_MEDICAL, 'label' => 'Medical Emergency'],
                ['value' => CommunityIncident::CATEGORY_UTILITY_OUTAGE, 'label' => 'Utility Outage'],
                ['value' => CommunityIncident::CATEGORY_SEVERE_WEATHER, 'label' => 'Severe Weather'],
                ['value' => CommunityIncident::CATEGORY_INFRASTRUCTURE, 'label' => 'Infrastructure'],
                ['value' => CommunityIncident::CATEGORY_GENERAL, 'label' => 'General Advisory'],
            ],
            'severities' => [
                ['value' => CommunityIncident::SEVERITY_LOW, 'label' => 'Low Advisory'],
                ['value' => CommunityIncident::SEVERITY_MEDIUM, 'label' => 'Medium Priority'],
                ['value' => CommunityIncident::SEVERITY_HIGH, 'label' => 'High Urgent'],
                ['value' => CommunityIncident::SEVERITY_CRITICAL, 'label' => 'Critical Emergency'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transformIncident(CommunityIncident $incident, bool $canManage): array
    {
        $updatesQuery = $incident->updates;
        if (! $canManage) {
            $updatesQuery = $updatesQuery->where('is_internal', false);
        }

        return [
            'id' => $incident->id,
            'incidentNumber' => $incident->incident_number,
            'title' => $incident->title,
            'category' => $incident->category,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'location' => $incident->location,
            'summary' => $incident->summary,
            // SENSITIVE DETAIL PROTECTION: Hidden unless authorized
            'sensitiveDetails' => $canManage ? $incident->sensitive_details : null,
            'broadcastSent' => $incident->broadcast_sent,
            'createdAt' => $incident->created_at?->toIso8601String(),
            'updatedAt' => $incident->updated_at?->toIso8601String(),
            'allClearAt' => $incident->all_clear_at?->toIso8601String(),
            'allClearMessage' => $incident->all_clear_message,
            'allClearBy' => $incident->allClearUser?->display_name,
            'reporter' => [
                'name' => $incident->reporter?->display_name ?? 'Security Dispatch',
                'role' => $incident->reporter?->role?->value,
            ],
            'updates' => $updatesQuery->map(fn (CommunityIncidentUpdate $u) => [
                'id' => $u->id,
                'message' => $u->message,
                'statusAfter' => $u->status_after,
                'isInternal' => $u->is_internal,
                'broadcastSent' => $u->broadcast_sent,
                'createdAt' => $u->created_at?->toIso8601String(),
                'author' => [
                    'name' => $u->author?->display_name ?? 'Security Officer',
                    'role' => $u->author?->role?->value,
                ],
            ])->values()->all(),
        ];
    }
}
