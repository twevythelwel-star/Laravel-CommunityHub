<?php

namespace App\Actions\Incidents;

use App\Actions\Communications\BroadcastEmergencyAction;
use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IssueIncidentAllClearAction
{
    public function __construct(
        private readonly ?BroadcastEmergencyAction $broadcaster = null
    ) {}

    /**
     * @param array{
     *     message: string,
     *     broadcast_all_clear?: bool|null,
     *     broadcast_channels?: array<string>|null
     * } $data
     */
    public function execute(CommunityIncident $incident, User $issuer, array $data): CommunityIncident
    {
        return DB::transaction(function () use ($incident, $issuer, $data) {
            $message = $data['message'];
            $shouldBroadcast = (bool) ($data['broadcast_all_clear'] ?? true);

            $incident->update([
                'status' => CommunityIncident::STATUS_ALL_CLEAR,
                'all_clear_at' => now(),
                'all_clear_by' => $issuer->id,
                'all_clear_message' => $message,
            ]);

            // Add all-clear update entry in timeline
            CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $issuer->id,
                'message' => "ALL-CLEAR ISSUED: {$message}",
                'status_after' => CommunityIncident::STATUS_ALL_CLEAR,
                'is_internal' => false,
                'broadcast_sent' => $shouldBroadcast,
            ]);

            if ($shouldBroadcast && $this->broadcaster !== null) {
                $channels = $data['broadcast_channels'] ?? ['in_app', 'push', 'sms', 'whatsapp', 'email'];

                $this->broadcaster->execute($issuer, [
                    'title' => "ALL-CLEAR: [{$incident->incident_number}] {$incident->title}",
                    'content' => "All-Clear issued by Security Operations: {$message}",
                    'severity' => 'advisory',
                    'audience' => 'all',
                    'send_in_app' => in_array('in_app', $channels, true),
                    'send_push' => in_array('push', $channels, true),
                    'send_sms' => in_array('sms', $channels, true),
                    'send_whatsapp' => in_array('whatsapp', $channels, true),
                    'send_email' => in_array('email', $channels, true),
                ]);
            }

            $issuer->recordActivity("Issued all-clear for incident {$incident->incident_number}");

            return $incident->fresh(['reporter', 'allClearUser', 'updates.author']);
        });
    }
}
