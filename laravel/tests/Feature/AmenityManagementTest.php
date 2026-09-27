<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\Landmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Administrators add, edit, deactivate and delete amenities, without being
 * able to strand an upcoming booking or erase booking history.
 */
class AmenityManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(UserRole::Admin)->create();
    }

    /** @return array<string, mixed> */
    private function fields(array $overrides = []): array
    {
        return [
            'name' => 'Rooftop Terrace',
            'category' => 'Community Center',
            'max_guests' => 20,
            'opens_at' => '08:00',
            'closes_at' => '22:00',
            'landmark_id' => null,
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function save(User $user, array $fields, ?Amenity $amenity = null): TestResponse
    {
        return $amenity
            ? $this->actingAs($user)->patch(route('dashboard.amenities.update', $amenity), $fields)
            : $this->actingAs($user)->post(route('dashboard.amenities.store'), $fields);
    }

    private function bookingOn(Amenity $amenity, array $overrides = []): AmenityBooking
    {
        return AmenityBooking::factory()->create([
            'amenity_id' => $amenity->id,
            'booked_on' => now()->addDays(3)->toDateString(),
            'slot' => 'evening',
            'guests' => 15,
            ...$overrides,
        ]);
    }

    public function test_an_administrator_adds_an_amenity(): void
    {
        $this->save($this->admin(), $this->fields())->assertSessionHasNoErrors();

        $amenity = Amenity::sole();
        $this->assertSame('Rooftop Terrace', $amenity->name);
        $this->assertSame(20, $amenity->max_guests);
        $this->assertTrue($amenity->isOpenFor('night'));
    }

    public function test_only_administrators_manage_amenities(): void
    {
        foreach ([UserRole::Homeowner, UserRole::Security, UserRole::Staff] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get(route('dashboard.amenities'))->assertForbidden();
            $this->save($user, $this->fields())->assertForbidden();
        }

        $this->assertSame(0, Amenity::count());
    }

    public function test_hours_must_close_after_opening_and_leave_a_bookable_slot(): void
    {
        $admin = $this->admin();

        $this->save($admin, $this->fields(['opens_at' => '18:00', 'closes_at' => '09:00']))->assertSessionHasErrors('closes_at');
        // 09:00-11:00 contains no whole slot (morning is 08:00-12:00).
        $this->save($admin, $this->fields(['opens_at' => '09:00', 'closes_at' => '11:00']))->assertSessionHasErrors('opens_at');

        $this->assertSame(0, Amenity::count());
    }

    public function test_names_are_unique_but_an_amenity_keeps_its_own(): void
    {
        $admin = $this->admin();
        $existing = Amenity::factory()->create(['name' => 'Rooftop Terrace']);

        $this->save($admin, $this->fields())->assertSessionHasErrors('name');
        $this->save($admin, $this->fields(['max_guests' => 25]), $existing)->assertSessionHasNoErrors();

        $this->assertSame(25, $existing->fresh()->max_guests);
    }

    public function test_capacity_cannot_drop_below_an_upcoming_booking(): void
    {
        $amenity = Amenity::factory()->create(['max_guests' => 20]);
        $this->bookingOn($amenity, ['guests' => 15]);

        $this->save($this->admin(), $this->fields(['name' => $amenity->name, 'max_guests' => 10]), $amenity)
            ->assertSessionHasErrors('max_guests');

        $this->assertSame(20, $amenity->fresh()->max_guests);
    }

    public function test_hours_cannot_exclude_an_upcoming_booking(): void
    {
        $amenity = Amenity::factory()->create();
        $this->bookingOn($amenity, ['slot' => 'evening']);

        // Closing at 16:00 would leave the 16:00-20:00 booking outside the hours.
        $this->save($this->admin(), $this->fields(['name' => $amenity->name, 'closes_at' => '16:00']), $amenity)
            ->assertSessionHasErrors('opens_at');

        $this->assertSame('22:00', substr((string) $amenity->fresh()->closes_at, 0, 5));
    }

    public function test_past_and_cancelled_bookings_do_not_block_an_edit(): void
    {
        $amenity = Amenity::factory()->create(['max_guests' => 20]);
        $this->bookingOn($amenity, ['booked_on' => now()->subDays(2)->toDateString(), 'guests' => 18]);
        $this->bookingOn($amenity, ['booked_on' => now()->addDays(4)->toDateString(), 'guests' => 18])->cancel();

        $this->save($this->admin(), $this->fields(['name' => $amenity->name, 'max_guests' => 5]), $amenity)
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $amenity->fresh()->max_guests);
    }

    public function test_a_deactivated_amenity_takes_no_new_bookings_and_keeps_existing_ones(): void
    {
        $amenity = Amenity::factory()->create();
        $booking = $this->bookingOn($amenity);

        $this->save($this->admin(), $this->fields(['name' => $amenity->name, 'is_active' => false]), $amenity)
            ->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->role(UserRole::Homeowner)->create())
            ->post(route('dashboard.amenity-bookings.store'), [
                'amenity_id' => $amenity->id,
                'booked_on' => now()->addDays(5)->toDateString(),
                'slot' => 'afternoon',
                'guests' => 2,
            ])->assertSessionHasErrors('amenity_id');

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_an_amenity_with_no_bookings_can_be_deleted(): void
    {
        $amenity = Amenity::factory()->create();

        $this->actingAs($this->admin())->delete(route('dashboard.amenities.destroy', $amenity))->assertSessionHasNoErrors();

        $this->assertModelMissing($amenity);
    }

    public function test_an_amenity_with_booking_history_is_not_deleted(): void
    {
        $amenity = Amenity::factory()->create();
        $booking = $this->bookingOn($amenity);
        $booking->cancel();

        $this->actingAs($this->admin())->delete(route('dashboard.amenities.destroy', $amenity))->assertSessionHasErrors('amenity');

        $this->assertModelExists($amenity);
        $this->assertModelExists($booking);
    }

    public function test_linking_a_landmark_puts_reserve_on_that_map_pin(): void
    {
        $landmark = Landmark::create([
            'community_id' => Community::default()->id,
            'name' => 'Central Recreation Park',
            'category' => 'Park',
            'lat' => 18.4775,
            'lng' => -77.9268,
        ]);

        $this->save($this->admin(), $this->fields(['landmark_id' => $landmark->id]))->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->role(UserRole::Homeowner)->create())->get('/dashboard/map')
            ->assertInertia(fn (Assert $page) => $page->where('amenities.0.landmarkId', $landmark->id));
    }

    public function test_the_page_lists_upcoming_bookings_with_who_made_them(): void
    {
        $amenity = Amenity::factory()->create(['name' => 'Pool']);
        $resident = User::factory()->role(UserRole::Homeowner)->create(['display_name' => 'Marcus V', 'lot' => 'Lot 42']);
        $this->bookingOn($amenity, ['user_id' => $resident->id]);
        $this->bookingOn($amenity, ['booked_on' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin())->get(route('dashboard.amenities'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Amenities')
                ->has('amenities', 1)
                ->where('amenities.0.hasBookings', true)
                ->has('amenities.0.upcomingBookings', 1)
                ->where('amenities.0.upcomingBookings.0.residentName', 'Marcus V'));
    }

    public function test_an_administrator_cancels_a_residents_booking(): void
    {
        $booking = $this->bookingOn(Amenity::factory()->create());

        $this->actingAs($this->admin())->delete(route('dashboard.amenity-bookings.destroy', $booking))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}
