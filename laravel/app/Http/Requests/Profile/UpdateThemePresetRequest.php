<?php

namespace App\Http\Requests\Profile;

use App\Models\BrandingSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'theme_preset' => ['nullable', 'string', Rule::in([...array_keys(BrandingSetting::THEME_PRESETS), 'default', 'community'])],
        ];
    }
}
