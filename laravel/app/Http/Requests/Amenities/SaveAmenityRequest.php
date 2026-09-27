<?php

namespace App\Http\Requests\Amenities;

use App\Models\Amenity;
use App\Models\AmenityBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Creating or editing an amenity. */
class SaveAmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAmenities') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $amenity = $this->route('amenity');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('amenities', 'name')->ignore($amenity?->id)],
            'category' => ['nullable', 'string', 'max:60'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:1000'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'landmark_id' => ['nullable', 'integer', Rule::exists('landmarks', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Another amenity already has that name.',
            'closes_at.after' => 'Closing time must be after opening time.',
        ];
    }

    /**
     * Hours must leave at least one bookable slot, and an edit must not strand
     * an upcoming booking the amenity would no longer allow.
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

                $proposed = new Amenity([
                    'opens_at' => $this->input('opens_at'),
                    'closes_at' => $this->input('closes_at'),
                ]);

                $bookableSlots = array_filter(array_keys(AmenityBooking::SLOTS), fn (string $slot) => $proposed->isOpenFor($slot));
                if ($bookableSlots === []) {
                    $validator->errors()->add('opens_at', 'These hours leave no bookable slot. Slots run 08:00-12:00, 12:00-16:00, 16:00-20:00 and 20:00-22:00.');

                    return;
                }

                /** @var Amenity|null $amenity */
                $amenity = $this->route('amenity');
                if (! $amenity) {
                    return;
                }

                $upcoming = $amenity->bookings()->confirmed()->whereDate('booked_on', '>=', today())->get(['slot', 'guests']);

                $tooManyGuests = $upcoming->where('guests', '>', $this->integer('max_guests'))->count();
                if ($tooManyGuests > 0) {
                    $validator->errors()->add('max_guests', "{$tooManyGuests} upcoming booking(s) have more guests than that. Cancel them first, or keep a higher capacity.");
                }

                $outsideHours = $upcoming->reject(fn (AmenityBooking $b) => $proposed->isOpenFor($b->slot))->count();
                if ($outsideHours > 0) {
                    $validator->errors()->add('opens_at', "{$outsideHours} upcoming booking(s) fall outside those hours. Cancel them first, or keep hours that cover them.");
                }
            },
        ];
    }
}
