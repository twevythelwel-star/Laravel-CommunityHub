<?php

namespace App\Http\Requests\Incidents;

use Illuminate\Foundation\Http\FormRequest;

class CloseIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $incident = $this->route('incident');

        return $incident && (bool) $this->user()?->can('close', $incident);
    }

    public function rules(): array
    {
        return [];
    }
}
