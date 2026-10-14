<?php

namespace App\Http\Requests\Blocklist;

use Illuminate\Foundation\Http\FormRequest;

class RequestBlocklistRemovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->role && $user->role->isResident());
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
