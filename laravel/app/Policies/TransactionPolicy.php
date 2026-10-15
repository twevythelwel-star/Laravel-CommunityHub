<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /** A payment receipt is for the payer and those who manage billing. */
    public function viewReceipt(User $user, Transaction $transaction): Response
    {
        return $transaction->user_id === $user->id || $user->can('manageBilling')
            ? Response::allow()
            : Response::deny('You can only download your own payment receipts.');
    }
}
