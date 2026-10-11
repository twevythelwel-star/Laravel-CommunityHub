<?php

namespace App\Http\Requests\GateDevice;

use Illuminate\Foundation\Http\FormRequest;

class GateDeviceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('gate_device') !== null;
    }

    public function rules(): array
    {
        return [
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'telemetry' => ['nullable', 'array'],
        ];
    }
}
