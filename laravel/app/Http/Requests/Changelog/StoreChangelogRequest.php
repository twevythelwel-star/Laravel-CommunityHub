<?php

namespace App\Http\Requests\Changelog;

use Illuminate\Foundation\Http\FormRequest;

class StoreChangelogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAppChangelog') ?? false;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:20'],
            'released_on' => ['required', 'date'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
