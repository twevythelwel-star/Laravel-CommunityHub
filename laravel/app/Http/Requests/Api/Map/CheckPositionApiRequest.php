<?php

namespace App\Http\Requests\Api\Map;

use Illuminate\Foundation\Http\FormRequest;

class CheckPositionApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
