<?php

namespace App\Actions\Incidents;

use App\Actions\Communications\BroadcastEmergencyAction;
use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportCommunityIncidentAction
{
    public function __construct(
        private readonly ?BroadcastEmergencyAction $broadcaster = null
    ) {}

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
        return DB::transaction(function () use ($reporter, $data) {
            $incidentNumber = CommunityIncident::generateIncidentNumber();
            $shouldBroadcast = (bool) ($data['broadcast_urgent_notice'] ?? false);

            $incident = CommunityIncident::create([
                'incident_number' => $incidentNumber,
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

            // Initial timeline update
            CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $reporter->id,
                'message' => 'Incident reported: ' . $data['summary'],
                'status_after' => CommunityIncident::STATUS_REPORTED,
                'is_internal' => false,
                'broadcast_sent' => $shouldBroadcast,
            ]);

            // If urgent notice requested, dispatch broadcast across channels
            if ($shouldBroadcast && $this->broadcaster !== null) {
                $severity = in_array($incident->severity, [CommunityIncident::SEVERITY_HIGH, CommunityIncident::SEVERITY_CRITICAL], true)
                    ? 'emergency'
                    : 'advisory';

                $channels = $data['broadcast_channels'] ?? ['in_app', 'push', 'sms', 'whatsapp', 'email'];

                $this->broadcaster->execute($reporter, [
                    'title' => "[{$incident->incident_number}] {$incident->title}",
                    'content' => "{$incident->summary} Location: {$incident->location}.",
                    'severity' => $severity,
                    'audience' => 'all',
                    'send_in_app' => in_array('in_app', $channels, true),
                    'send_push' => in_array('push', $channels, true),
                    'send_sms' => in_array('sms', $channels, true),
                    'send_whatsapp' => in_array('whatsapp', $channels, true),
                    'send_email' => in_array('email', $channels, true),
                ]);
            }

            $reporter->recordActivity("Reported community incident {$incident->incident_number}: {$incident->title}");

            return $incident;
        });
    }
}
