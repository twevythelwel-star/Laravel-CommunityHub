<?php

namespace App\Http\Requests\Directory;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageUsers') ?? false;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['sometimes', 'string', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:120'],
            'lot' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:Active,Inactive'],
        ];
    }

    /**
     * An administrator cannot deactivate their own account here and lock
     * themselves out.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $account = $this->route('user');

            if ($account instanceof User && $account->is($this->user()) && $this->input('status') === 'Inactive') {
                $validator->errors()->add('status', 'You cannot deactivate your own account here.');
            }
        }];
    }
}
