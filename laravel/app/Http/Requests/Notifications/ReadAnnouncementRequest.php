<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/** A resident marking a notice they can see as read. */
class ReadAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement && $this->user()?->can('view', $announcement);
    }

    public function rules(): array
    {
        return [];
    }
}
