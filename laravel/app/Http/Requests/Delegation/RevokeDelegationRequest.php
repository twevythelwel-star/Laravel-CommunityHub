<?php

namespace App\Http\Requests\Delegation;

use App\Models\DelegatedAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RevokeDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        /** @var DelegatedAccess|null $delegation */
        $delegation = $this->route('delegation');
        if (! $delegation) {
            return true;
        }

        $response = Gate::forUser($user)->inspect('revoke', [$delegation, $this->all()]);
        if ($response->denied()) {
            abort(403, $response->message() ?: 'This action is unauthorized.');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
