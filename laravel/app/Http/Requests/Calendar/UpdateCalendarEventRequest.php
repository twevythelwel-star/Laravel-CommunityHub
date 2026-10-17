<?php

namespace App\Http\Requests\Calendar;

use App\Models\CommunityEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('broadcastNotices') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'event_type' => ['sometimes', 'string', Rule::in(array_keys(CommunityEvent::TYPES))],
            'location' => ['nullable', 'string', 'max:255'],
            'is_all_day' => ['sometimes', 'boolean'],
            'remind_before_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'change_summary' => ['nullable', 'string', 'max:500'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
