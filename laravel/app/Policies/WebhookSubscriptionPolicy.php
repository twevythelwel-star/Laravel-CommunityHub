<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Auth\Access\Response;

class WebhookSubscriptionPolicy
{
    /**
     * Its owner, or whoever operates the platform. Deliberately not
     * tokenCan('*'): a session-authenticated request carries Sanctum's
     * TransientToken, which "can" everything, so that check let any
     * signed-in browser user act on anybody's subscription.
     */
    public function manage(User $user, WebhookSubscription $subscription): Response
    {
        return $subscription->user_id === $user->id || $user->can('operatePlatform')
            ? Response::allow()
            : Response::deny('Unauthorized access to this webhook subscription.');
    }
}
