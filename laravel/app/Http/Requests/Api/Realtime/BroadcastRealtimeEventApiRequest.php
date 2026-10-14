<?php

namespace App\Http\Requests\Api\Realtime;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastRealtimeEventApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $type = $this->input('type');
        $ability = match ($type) {
            'chat' => null,
            'notification' => 'broadcastNotices',
            default => 'manageSecurity',
        };

        return $ability === null || $user->can($ability);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:chat,telemetry,notification,pass,operations'],
            'room_id' => ['nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pass_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:40'],
            'severity' => ['nullable', 'string', 'in:info,warning,critical'],
            'headline' => ['nullable', 'string', 'max:160'],
            'telemetry' => ['nullable', 'array'],
        ];
    }
}
