<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Webhooks;

use App\Models\WebhookSubscription;
use Illuminate\Foundation\Http\FormRequest;

class TestWebhookSubscriptionApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $subscription = $this->route('subscription');
        if (! $subscription instanceof WebhookSubscription) {
            return true;
        }

        return $subscription->user_id === $user->id || $user->can('operatePlatform');
    }

    public function rules(): array
    {
        return [];
    }
}
