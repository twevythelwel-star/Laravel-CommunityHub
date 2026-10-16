<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class CancelAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $announcement = $this->route('announcement');

        return $announcement && $this->user()?->can('cancel', $announcement);
    }

    public function rules(): array
    {
        return [];
    }
}
