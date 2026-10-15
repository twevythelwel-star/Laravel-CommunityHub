<?php

namespace App\Services\People;

use App\Enums\UserRole;
use App\Models\Renter;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * A temporary homeowner manages visitors only while their stay lasts, and
 * only for visits that fall inside it. Everyone else passes straight
 * through. Used by single and bulk registration, editing and removal, which
 * each had their own copy of this check.
 */
class TemporaryStayGuard
{
    /**
     * @param  string  $doing  What the user is trying to do, for the message: "register", "modify", "remove".
     * @param  CarbonInterface|null  $visitAt  The visit's time, when one is being set.
     * @param  string  $field  The form field the refusal is reported on.
     *
     * @throws ValidationException
     */
    public function ensureCanManageVisitors(User $user, string $doing, ?CarbonInterface $visitAt = null, string $field = 'expected_at'): void
    {
        if ($user->role !== UserRole::TemporaryHomeowner) {
            return;
        }

        $stay = $user->activeStay() ?? Renter::where('user_id', $user->id)->first();

        if (! $stay || $stay->leaseHasExpired()) {
            throw ValidationException::withMessages([
                $field => "Your temporary stay has expired or is inactive. You cannot {$doing} visitors.",
            ]);
        }

        if ($visitAt && ($visitAt->lt($stay->lease_start->copy()->startOfDay()) || $visitAt->gt($stay->lease_end->copy()->endOfDay()))) {
            throw ValidationException::withMessages([
                $field => "Visitors can only be scheduled within your approved stay timeframe ({$stay->lease_start->format('M d, Y')} to {$stay->lease_end->format('M d, Y')}).",
            ]);
        }
    }
}
