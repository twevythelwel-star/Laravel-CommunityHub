<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    /**
     * Determine whether the user can view or bring back the payment.
     */
    public function view(User $user, Payment $payment): Response
    {
        return $payment->user_id === $user->id || $user->can('manageBilling')
            ? Response::allow()
            : Response::deny('Unauthorized access to payment.');
    }
}
