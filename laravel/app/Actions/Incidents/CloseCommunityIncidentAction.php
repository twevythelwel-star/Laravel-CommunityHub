<?php

namespace App\Actions\Incidents;

use App\Models\CommunityIncident;
use App\Models\CommunityIncidentUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Closes an incident's record, noting who closed it on its timeline. */
class CloseCommunityIncidentAction
{
    public function execute(CommunityIncident $incident, User $closer): void
    {
        DB::transaction(function () use ($incident, $closer) {
            $incident->update(['status' => CommunityIncident::STATUS_CLOSED]);

            CommunityIncidentUpdate::create([
                'incident_id' => $incident->id,
                'user_id' => $closer->id,
                'message' => 'Incident closed.',
                'status_after' => CommunityIncident::STATUS_CLOSED,
                'is_internal' => false,
                'broadcast_sent' => false,
            ]);
        });

        $closer->recordActivity("Closed incident {$incident->incident_number}");
    }
}
