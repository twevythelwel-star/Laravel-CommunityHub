<?php

namespace App\Policies;

use App\Models\Donation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DonationPolicy
{
    /**
     * A receipt names the donor and the amount, including for a gift marked
     * anonymous (that flag only governs the public feed), so it is for the
     * donor and those who manage fundraisers.
     */
    public function viewReceipt(User $user, Donation $donation): Response
    {
        return $donation->user_id === $user->id || $user->can('manageFundraisers')
            ? Response::allow()
            : Response::deny('You can only view your own donation receipts.');
    }
}
