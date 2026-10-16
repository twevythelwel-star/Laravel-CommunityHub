<?php

namespace App\Http\Requests\Maintenance;

use App\Models\MaintenanceIssue;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admins, staff and security triage; see MaintenanceIssuePolicy.
        return (bool) $this->user()?->can('triage', MaintenanceIssue::class);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                'in:reported,triaged,assigned,in_progress,resolved,closed,cancelled',
            ],
            'priority' => [
                'nullable',
                'string',
                'in:low,medium,high,emergency',
            ],
            'assigned_to_user_id' => [
                'nullable',
                'exists:users,id',
            ],
            'note' => ['nullable', 'string', 'max:2000'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'is_internal' => ['nullable', 'boolean'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
