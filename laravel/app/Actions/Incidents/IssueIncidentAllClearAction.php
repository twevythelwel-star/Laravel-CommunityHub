<?php

namespace App\Actions\Incidents;

use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use App\Services\Incidents\IncidentBroadcaster;
use Illuminate\Support\Facades\DB;

/**
 * Declares an incident over: records the all-clear on it and its timeline and, unless told
 * not to, announces it to the community once saved.
 */
class IssueIncidentAllClearAction
{
    public function __construct(private readonly IncidentBroadcaster $broadcaster) {}

    /**
     * @param array{
     *     message: string,
     *     broadcast_all_clear?: bool|null,
     *     broadcast_channels?: array<string>|null
     * } $data
     */
    public function execute(CommunityIncident $incident, User $issuer, array $data): CommunityIncident
    {
        $message = $data['message'];
        $shouldBroadcast = (bool) ($data['broadcast_all_clear'] ?? true);

        DB::transaction(function () use ($incident, $issuer, $message, $shouldBroadcast) {
            $incident->update([
                'status' => CommunityIncident::STATUS_ALL_CLEAR,
                'all_clear_at' => now(),
                'all_clear_by' => $issuer->id,
                'all_clear_message' => $message,
            ]);

            CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $issuer->id,
                'message' => "ALL-CLEAR ISSUED: {$message}",
                'status_after' => CommunityIncident::STATUS_ALL_CLEAR,
                'is_internal' => false,
                'broadcast_sent' => $shouldBroadcast,
            ]);
        });

        if ($shouldBroadcast) {
            $this->broadcaster->send(
                $issuer,
                "ALL-CLEAR: [{$incident->incident_number}] {$incident->title}",
                "All-Clear issued by Security Operations: {$message}",
                'advisory',
                $data['broadcast_channels'] ?? null,
            );
        }

        $issuer->recordActivity("Issued all-clear for incident {$incident->incident_number}");

        return $incident->fresh(['reporter', 'allClearUser', 'updates.author']);
    }
}
