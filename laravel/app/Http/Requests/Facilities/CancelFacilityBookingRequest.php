<?php

namespace App\Http\Requests\Facilities;

use Illuminate\Foundation\Http\FormRequest;

class CancelFacilityBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('facilityBooking');

        return $booking && (bool) $this->user()?->can('cancel', $booking);
    }

    public function rules(): array
    {
        return [];
    }
}
