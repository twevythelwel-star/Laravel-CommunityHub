<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): Response
    {
        return $invoice->user_id === $user->id || $user->can('manageBilling')
            ? Response::allow()
            : Response::deny('You can only view your own statements.');
    }

    /**
     * Determine whether the user can pay the invoice.
     */
    public function pay(User $user, Invoice $invoice): Response
    {
        return $invoice->user_id === $user->id || $user->can('manageBilling')
            ? Response::allow()
            : Response::deny('Unauthorized to pay this statement.');
    }
}
