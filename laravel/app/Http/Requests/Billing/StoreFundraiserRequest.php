<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class StoreFundraiserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageFundraisers') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'beneficiary' => ['nullable', 'string', 'max:150'],
            'goal' => ['required', 'numeric', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:Active,Upcoming'],
            'cover_image_url' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable'],
            'matching_sponsor' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * The gallery image URLs: a list, or one URL per line from the form's
     * textarea.
     *
     * @return list<string>
     */
    public function images(): array
    {
        $images = $this->validated('images');

        return match (true) {
            is_array($images) => array_values($images),
            is_string($images) => array_values(array_filter(array_map('trim', explode("\n", $images)))),
            default => [],
        };
    }
}
