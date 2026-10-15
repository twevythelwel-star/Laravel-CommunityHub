<?php

namespace App\Actions\Visitors;

use App\Models\BlocklistEntry;
use App\Models\User;
use App\Models\Visitor;
use App\Services\GatePassEngine;
use App\Services\People\TemporaryStayGuard;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class UpdateVisitorAction
{
    public function __construct(
        protected GatePassEngine $engine,
        protected TemporaryStayGuard $stay,
    ) {}

    /**
     * Update visitor details, verifying blocklist and stay bounds, and syncing pass.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    public function execute(Visitor $visitor, array $validated, User $user): void
    {
        // Server-side blocklist check if name was changed
        if (isset($validated['name']) && $validated['name'] !== $visitor->name) {
            if (BlocklistEntry::blocks($validated['name'])) {
                throw ValidationException::withMessages([
                    'name' => 'This person is on the community blocklist and cannot be registered. Contact security.',
                ]);
            }
        }

        $this->stay->ensureCanManageVisitors($user, 'modify', isset($validated['expected_at']) ? Carbon::parse($validated['expected_at']) : null);

        $visitor->update($validated);

        if ($visitor->gatePass) {
            $this->engine->syncGuestPass($visitor->gatePass, $visitor->fresh());
        }
    }
}
