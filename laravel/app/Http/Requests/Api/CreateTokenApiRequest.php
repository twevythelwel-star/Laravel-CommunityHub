<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

class CreateTokenApiRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required_without:token_name', 'string', 'min:2', 'max:100'],
            'token_name' => ['required_without:name', 'string', 'min:2', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:50'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
