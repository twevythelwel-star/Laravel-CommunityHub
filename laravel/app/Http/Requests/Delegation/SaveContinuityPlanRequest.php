<?php

namespace App\Http\Requests\Delegation;

use App\Models\DelegatedAccess;
use App\Models\EmergencyContinuityPlan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveContinuityPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $response = Gate::forUser($user)->inspect('saveContinuityPlan', [DelegatedAccess::class, $this->all()]);

        if ($response->denied()) {
            throw new AuthorizationException($response->message() ?: 'Emergency & Incapacity Continuity Plans are reserved for property homeowners.');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'primary_delegate_id' => ['nullable', 'exists:delegated_accesses,id'],
            'secondary_delegate_id' => ['nullable', 'exists:delegated_accesses,id'],
            'activation_conditions' => ['required', 'array', 'min:1'],
            'activation_conditions.*' => ['string', 'in:'.implode(',', array_keys(EmergencyContinuityPlan::CONDITIONS))],
            'required_verification' => ['required', 'string', 'in:'.implode(',', array_keys(EmergencyContinuityPlan::VERIFICATIONS))],
            'authorized_actions' => ['required', 'array', 'min:1'],
            'authorized_actions.*' => ['string', 'in:'.implode(',', array_keys(EmergencyContinuityPlan::ACTIONS))],
            'max_duration_days' => ['required', 'integer', 'min:1', 'max:180'],
            'requires_admin_approval' => ['required', 'boolean'],
            'notify_homeowner_on_trigger' => ['required', 'boolean'],
            'notify_community_security' => ['required', 'boolean'],
            'notification_recipients' => ['nullable', 'array'],
            'special_instructions' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
