<?php

namespace App\Actions\Visitors;

use App\Models\BlocklistRemovalRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * An administrator's answer to a resident's blocklist removal request.
 * Approving is what actually lifts the block: the entry is deleted and the
 * removal goes to the security log.
 */
class ReviewBlocklistRemovalAction
{
    /**
     * @param  array{status: string, reviewer_note?: ?string}  $validated
     * @return bool Whether the entry was removed.
     */
    public function execute(BlocklistRemovalRequest $removalRequest, User $reviewer, array $validated): bool
    {
        $removalRequest->update([
            ...$validated,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['status'] !== 'Approved') {
            return false;
        }

        $entry = $removalRequest->entry;

        Log::channel('security')->notice('Blocklist entry removed after resident request', [
            'name' => $entry?->name,
            'removed_by' => $reviewer->uid,
            'requested_by' => $removalRequest->requester?->uid,
        ]);

        $entry?->delete();

        return true;
    }
}
