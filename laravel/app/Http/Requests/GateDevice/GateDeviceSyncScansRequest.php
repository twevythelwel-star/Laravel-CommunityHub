<?php

namespace App\Http\Requests\GateDevice;

use Illuminate\Foundation\Http\FormRequest;

class GateDeviceSyncScansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('gate_device') !== null;
    }

    public function rules(): array
    {
        return [
            'scans' => ['required', 'array'],
            'scans.*.offline_id' => ['required', 'string'],
            'scans.*.token_or_pin' => ['required', 'string'],
            'scans.*.method' => ['nullable', 'string'],
            'scans.*.scanned_at' => ['nullable', 'date'],
            'scans.*.action' => ['nullable', 'string'],
            'scans.*.notes' => ['nullable', 'string'],
            'gate' => ['nullable', 'string'],
        ];
    }
}
