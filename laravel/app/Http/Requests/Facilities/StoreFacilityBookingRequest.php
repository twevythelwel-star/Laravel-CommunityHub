<?php

namespace App\Http\Requests\Facilities;

use App\Models\FacilityBooking;
use App\Models\Landmark;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', FacilityBooking::class);
    }

    public function rules(): array
    {
        $facility = Landmark::find($this->input('landmark_id'));

        return [
            'landmark_id' => ['required', 'integer', Rule::exists('landmarks', 'id')->where('bookable', true)],
            'booked_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.now()->addMonths(6)->toDateString()],
            'slot' => ['required', 'string', Rule::in(array_keys(FacilityBooking::SLOTS))],
            'guests' => ['required', 'integer', 'min:1', 'max:'.($facility?->guestLimit() ?? FacilityBooking::DEFAULT_CAPACITY)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'landmark_id.exists' => 'That facility cannot be booked.',
            'booked_on.after_or_equal' => 'Choose today or a later date.',
            'booked_on.before_or_equal' => 'Bookings can be made up to six months ahead.',
            'guests.max' => 'That is more guests than this facility allows.',
        ];
    }
}
