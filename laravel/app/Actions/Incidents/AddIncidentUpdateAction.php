<?php

namespace App\Actions\Incidents;

use App\Actions\Communications\BroadcastEmergencyAction;
use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddIncidentUpdateAction
{
    public function __construct(
        private readonly ?BroadcastEmergencyAction $broadcaster = null
    ) {}

    /**
     * @param array{
     *     message: string,
     *     status?: string|null,
     *     is_internal?: bool|null,
     *     broadcast_update?: bool|null,
     *     broadcast_channels?: array<string>|null
     * } $data
     */
    public function execute(CommunityIncident $incident, User $author, array $data): CommunityIncidentUpdate
    {
        return DB::transaction(function () use ($incident, $author, $data) {
            $statusAfter = $data['status'] ?? $incident->status;
            $isInternal = (bool) ($data['is_internal'] ?? false);
            $shouldBroadcast = !$isInternal && (bool) ($data['broadcast_update'] ?? false);

            if ($statusAfter !== $incident->status) {
                $incident->update(['status' => $statusAfter]);
            }

            $update = CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $author->id,
                'message' => $data['message'],
                'status_after' => $statusAfter,
                'is_internal' => $isInternal,
                'broadcast_sent' => $shouldBroadcast,
            ]);

            if ($shouldBroadcast && $this->broadcaster !== null) {
                $channels = $data['broadcast_channels'] ?? ['in_app', 'push', 'sms', 'whatsapp', 'email'];

                $this->broadcaster->execute($author, [
                    'title' => "UPDATE: [{$incident->incident_number}] {$incident->title}",
                    'content' => $data['message'],
                    'severity' => 'advisory',
                    'audience' => 'all',
                    'send_in_app' => in_array('in_app', $channels, true),
                    'send_push' => in_array('push', $channels, true),
                    'send_sms' => in_array('sms', $channels, true),
                    'send_whatsapp' => in_array('whatsapp', $channels, true),
                    'send_email' => in_array('email', $channels, true),
                ]);
            }

            $noteType = $isInternal ? 'internal note' : 'public update';
            $author->recordActivity("Added {$noteType} to incident {$incident->incident_number}");

            return $update;
        });
    }
}
