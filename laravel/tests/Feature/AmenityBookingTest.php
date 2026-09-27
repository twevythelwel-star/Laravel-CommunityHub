<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Amenity reservations are stored, checked against the amenity's capacity and
 * hours, and a slot can be held by one resident at a time.
 */
class AmenityBookingTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        return User::factory()->role(UserRole::Homeowner)->create();
    }

    private function pool(): Amenity
    {
        return Amenity::factory()->create(['name' => 'Pool', 'max_guests' => 30, 'opens_at' => '07:00', 'closes_at' => '20:00']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function book(User $user, Amenity $amenity, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->post(route('dashboard.amenity-bookings.store'), [
            'amenity_id' => $amenity->id,
            'booked_on' => now()->addDays(2)->toDateString(),
            'slot' => 'afternoon',
            'guests' => 6,
            'notes' => 'Birthday',
            ...$overrides,
        ]);
    }

    public function test_a_resident_books_an_amenity_and_gets_a_real_reference(): void
    {
        $resident = $this->resident();
        $pool = $this->pool();

        $this->book($resident, $pool)->assertSessionHasNoErrors()->assertSessionHas('booking');

        $booking = AmenityBooking::sole();
        $this->assertSame($resident->id, $booking->user_id);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(6, $booking->guests);
        $this->assertMatchesRegularExpression('/^BK-\d{4}-[A-Z0-9]{8}$/', $booking->reference);
        $this->assertSame(['reference' => $booking->reference], session('booking'));
    }

    public function test_a_slot_cannot_be_booked_twice(): void
    {
        $pool = $this->pool();
        $this->book($this->resident(), $pool)->assertSessionHasNoErrors();

        $this->book($this->resident(), $pool)->assertSessionHasErrors('slot');

        $this->assertSame(1, AmenityBooking::count());
    }

    public function test_the_same_slot_on_another_amenity_or_day_is_free(): void
    {
        $pool = $this->pool();
        $this->book($this->resident(), $pool)->assertSessionHasNoErrors();

        $this->book($this->resident(), $pool, ['booked_on' => now()->addDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->book($this->resident(), Amenity::factory()->create())->assertSessionHasNoErrors();

        $this->assertSame(3, AmenityBooking::count());
    }

    public function test_guests_cannot_exceed_the_amenity_capacity(): void
    {
        $this->book($this->resident(), $this->pool(), ['guests' => 31])->assertSessionHasErrors('guests');
        $this->assertSame(0, AmenityBooking::count());
    }

    public function test_a_slot_outside_opening_hours_is_refused(): void
    {
        // The pool closes at 20:00; the night slot runs 20:00-22:00.
        $this->book($this->resident(), $this->pool(), ['slot' => 'night'])->assertSessionHasErrors('slot');
        $this->assertSame(0, AmenityBooking::count());
    }

    public function test_past_dates_unknown_slots_and_inactive_amenities_are_refused(): void
    {
        $resident = $this->resident();
        $pool = $this->pool();

        $this->book($resident, $pool, ['booked_on' => now()->subDay()->toDateString()])->assertSessionHasErrors('booked_on');
        $this->book($resident, $pool, ['slot' => 'midnight'])->assertSessionHasErrors('slot');
        $this->book($resident, Amenity::factory()->inactive()->create())->assertSessionHasErrors('amenity_id');

        $this->assertSame(0, AmenityBooking::count());
    }

    public function test_a_slot_that_has_already_started_today_is_refused(): void
    {
        $this->travelTo(today()->setTime(13, 0));
        $pool = $this->pool();

        $this->book($this->resident(), $pool, ['booked_on' => today()->toDateString(), 'slot' => 'afternoon'])
            ->assertSessionHasErrors('slot');
        $this->book($this->resident(), $pool, ['booked_on' => today()->toDateString(), 'slot' => 'evening'])
            ->assertSessionHasNoErrors();
    }

    public function test_only_residents_and_administrators_can_book(): void
    {
        $pool = $this->pool();

        $this->book(User::factory()->role(UserRole::Security)->create(), $pool)->assertForbidden();
        $this->book(User::factory()->role(UserRole::Staff)->create(), $pool)->assertForbidden();
        $this->book(User::factory()->role(UserRole::Admin)->create(), $pool)->assertSessionHasNoErrors();
    }

    public function test_cancelling_frees_the_slot_and_keeps_the_record(): void
    {
        $resident = $this->resident();
        $pool = $this->pool();
        $this->book($resident, $pool);
        $booking = AmenityBooking::sole();

        $this->actingAs($resident)->delete(route('dashboard.amenity-bookings.destroy', $booking))->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertNull($booking->slot_key);
        $this->assertNotNull($booking->cancelled_at);

        $this->book($this->resident(), $pool)->assertSessionHasNoErrors();
    }

    public function test_a_resident_cannot_cancel_someone_elses_booking(): void
    {
        $this->book($this->resident(), $this->pool());

        $this->actingAs($this->resident())
            ->delete(route('dashboard.amenity-bookings.destroy', AmenityBooking::sole()))
            ->assertForbidden();

        $this->assertSame('confirmed', AmenityBooking::sole()->status);
    }

    public function test_the_map_lists_amenities_and_taken_slots_without_naming_who_holds_them(): void
    {
        $holder = $this->resident();
        $pool = $this->pool();
        $this->book($holder, $pool);
        Amenity::factory()->inactive()->create(['name' => 'Closed Sauna']);

        $this->actingAs($this->resident())->get('/dashboard/map')
            ->assertInertia(fn (Assert $page) => $page
                ->has('amenities', 1)
                ->where('amenities.0.name', 'Pool')
                ->where('amenities.0.openHours', '07:00 - 20:00')
                ->has('bookedSlots', 1)
                ->where('bookedSlots.0', ['amenityId' => $pool->id, 'date' => now()->addDays(2)->toDateString(), 'slot' => 'afternoon'])
                ->has('myBookings', 0));

        $this->actingAs($holder)->get('/dashboard/map')
            ->assertInertia(fn (Assert $page) => $page->has('myBookings', 1)->where('myBookings.0.amenityName', 'Pool'));
    }
}
