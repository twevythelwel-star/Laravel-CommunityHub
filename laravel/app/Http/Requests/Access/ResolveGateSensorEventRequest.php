<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class ResolveGateSensorEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'exists:gate_sensor_events,id'],
            'status' => ['required', 'string', 'in:CLEAR,INVESTIGATING,CONFIRMED_VIOLATION,DISMISSED'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
