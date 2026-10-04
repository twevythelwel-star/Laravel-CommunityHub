<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

class CreateGatePassApiRequest extends ApiRequest
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
            'holder_name' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['nullable', 'string', 'in:VISITOR,CONTRACTOR,SERVICE,RESIDENT_DEPENDENT'],
            'designated_gate' => ['nullable', 'string'],
            'property' => ['required', 'string', 'max:120'],
            'access_zone' => ['nullable', 'string', 'max:80'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'single_entry' => ['nullable', 'boolean'],
        ];
    }
}
