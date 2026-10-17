<?php

namespace App\Actions\Incidents;

use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use App\Services\Incidents\IncidentBroadcaster;
use Illuminate\Support\Facades\DB;

/**
 * Adds a public update or an internal note to an incident, optionally moving its status. A
 * public update can be announced to the community, once it is saved; an internal note never is.
 */
class AddIncidentUpdateAction
{
    public function __construct(private readonly IncidentBroadcaster $broadcaster) {}

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
        $isInternal = (bool) ($data['is_internal'] ?? false);
        $shouldBroadcast = ! $isInternal && (bool) ($data['broadcast_update'] ?? false);

        $update = DB::transaction(function () use ($incident, $author, $data, $isInternal, $shouldBroadcast) {
            $statusAfter = $data['status'] ?? $incident->status;

            if ($statusAfter !== $incident->status) {
                $incident->update(['status' => $statusAfter]);
            }

            return CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $author->id,
                'message' => $data['message'],
                'status_after' => $statusAfter,
                'is_internal' => $isInternal,
                'broadcast_sent' => $shouldBroadcast,
            ]);
        });

        if ($shouldBroadcast) {
            $this->broadcaster->send(
                $author,
                "UPDATE: [{$incident->incident_number}] {$incident->title}",
                $data['message'],
                'advisory',
                $data['broadcast_channels'] ?? null,
            );
        }

        $noteType = $isInternal ? 'internal note' : 'public update';
        $author->recordActivity("Added {$noteType} to incident {$incident->incident_number}");

        return $update;
    }
}
