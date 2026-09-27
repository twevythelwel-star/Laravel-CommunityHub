<?php

namespace App\Http\Requests\Amenities;

use App\Models\Amenity;
use App\Models\AmenityBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAmenityBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accessCommunityLife') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amenity_id' => ['required', 'integer', Rule::exists('amenities', 'id')->where('is_active', true)],
            'booked_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'slot' => ['required', 'string', Rule::in(array_keys(AmenityBooking::SLOTS))],
            'guests' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amenity_id.exists' => 'That amenity is not available for booking.',
            'booked_on.after_or_equal' => 'Choose today or a later date.',
            'slot.in' => 'Choose one of the listed time slots.',
        ];
    }

    /**
     * Rules that depend on the amenity: its capacity and opening hours, and
     * that a slot starting today has not already begun.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $amenity = Amenity::find($this->integer('amenity_id'));
                $slot = (string) $this->input('slot');

                if ($this->integer('guests') > $amenity->max_guests) {
                    $validator->errors()->add('guests', "{$amenity->name} holds at most {$amenity->max_guests} guests.");
                }

                if (! $amenity->isOpenFor($slot)) {
                    $validator->errors()->add('slot', "{$amenity->name} is open {$amenity->openHoursLabel()}; that slot falls outside its hours.");
                }

                if ($this->input('booked_on') === today()->toDateString()
                    && AmenityBooking::SLOTS[$slot]['starts'] <= now()->format('H:i')) {
                    $validator->errors()->add('slot', 'That slot has already started today.');
                }
            },
        ];
    }
}
