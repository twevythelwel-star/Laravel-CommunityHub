<?php

namespace App\Actions\Incidents;

use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use App\Services\Incidents\IncidentBroadcaster;
use Illuminate\Support\Facades\DB;

/**
 * Security logs an incident with its first timeline entry and, if asked, announces it to the
 * community. The announcement goes out only once the incident is saved.
 */
class ReportCommunityIncidentAction
{
    public function __construct(private readonly IncidentBroadcaster $broadcaster) {}

    /**
     * @param array{
     *     title: string,
     *     category: string,
     *     severity: string,
     *     location: string,
     *     summary: string,
     *     sensitive_details?: string|null,
     *     broadcast_urgent_notice?: bool|null,
     *     broadcast_channels?: array<string>|null
     * } $data
     */
    public function execute(User $reporter, array $data): CommunityIncident
    {
        $shouldBroadcast = (bool) ($data['broadcast_urgent_notice'] ?? false);

        $incident = DB::transaction(function () use ($reporter, $data, $shouldBroadcast) {
            $incident = CommunityIncident::create([
                'incident_number' => CommunityIncident::generateIncidentNumber(),
                'title' => $data['title'],
                'category' => $data['category'] ?? CommunityIncident::CATEGORY_GENERAL,
                'severity' => $data['severity'] ?? CommunityIncident::SEVERITY_MEDIUM,
                'status' => CommunityIncident::STATUS_REPORTED,
                'location' => $data['location'],
                'summary' => $data['summary'],
                'sensitive_details' => $data['sensitive_details'] ?? null,
                'reported_by' => $reporter->id,
                'broadcast_sent' => $shouldBroadcast,
            ]);

            CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $reporter->id,
                'message' => 'Incident reported: '.$data['summary'],
                'status_after' => CommunityIncident::STATUS_REPORTED,
                'is_internal' => false,
                'broadcast_sent' => $shouldBroadcast,
            ]);

            return $incident;
        });

        if ($shouldBroadcast) {
            $urgent = in_array($incident->severity, [CommunityIncident::SEVERITY_HIGH, CommunityIncident::SEVERITY_CRITICAL], true);
            $this->broadcaster->send(
                $reporter,
                "[{$incident->incident_number}] {$incident->title}",
                "{$incident->summary} Location: {$incident->location}.",
                $urgent ? 'emergency' : 'advisory',
                $data['broadcast_channels'] ?? null,
            );
        }

        $reporter->recordActivity("Reported community incident {$incident->incident_number}: {$incident->title}");

        return $incident;
    }
}
