<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Community;
use App\Models\FacilityBooking;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\Landmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityBookingTest extends TestCase
{
    use RefreshDatabase;

    private Landmark $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $community = Community::create(['name' => 'Sunset Palms', 'code' => 'SP-01']);
        $this->pool = Landmark::create([
            'community_id' => $community->id, 'name' => 'Pool Pavilion', 'category' => 'Community Center',
            'lat' => 18.0, 'lng' => -76.8, 'bookable' => true, 'capacity' => 20,
        ]);
    }

    private function book(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->postJson('/dashboard/facility-bookings', array_merge([
            'landmark_id' => $this->pool->id,
            'booked_on' => now()->addDays(2)->toDateString(),
            'slot' => 'afternoon',
            'guests' => 6,
            'notes' => 'Birthday',
        ], $overrides));
    }

    public function test_a_resident_books_a_facility_and_is_notified(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();

        $response = $this->book($resident)->assertCreated()->assertJsonPath('facility', 'Pool Pavilion');

        $booking = FacilityBooking::sole();
        $this->assertSame($response->json('reference'), $booking->reference);
        $this->assertSame(['confirmed', $resident->id, 'afternoon', 6], [$booking->status, $booking->user_id, $booking->slot, $booking->guests]);
        $this->assertStringContainsString($booking->reference, InAppNotification::where('user_id', $resident->id)->sole()->body);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $resident->id, 'subject_type' => $booking->getMorphClass(), 'subject_id' => $booking->id, 'status' => 'sent',
        ]);
    }

    public function test_a_taken_slot_cannot_be_booked_twice_but_another_slot_can(): void
    {
        $first = User::factory()->role(UserRole::Homeowner)->create();
        $second = User::factory()->role(UserRole::Homeowner)->create();

        $this->book($first)->assertCreated();
        $this->book($second)->assertUnprocessable()->assertJsonValidationErrors('slot');
        $this->book($second, ['slot' => 'evening'])->assertCreated();

        $this->assertSame(2, FacilityBooking::count());
    }

    public function test_a_cancelled_slot_is_free_again(): void
    {
        $first = User::factory()->role(UserRole::Homeowner)->create();
        $second = User::factory()->role(UserRole::Homeowner)->create();
        $this->book($first)->assertCreated();
        $booking = FacilityBooking::sole();

        $this->actingAs($first)->postJson("/dashboard/facility-bookings/{$booking->id}/cancel")->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->book($second)->assertCreated();
    }

    public function test_only_the_booker_or_an_administrator_can_cancel(): void
    {
        $owner = User::factory()->role(UserRole::Homeowner)->create();
        $neighbour = User::factory()->role(UserRole::Homeowner)->create();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->book($owner)->assertCreated();
        $booking = FacilityBooking::sole();

        $this->actingAs($neighbour)->postJson("/dashboard/facility-bookings/{$booking->id}/cancel")->assertForbidden();
        $this->assertSame('confirmed', $booking->fresh()->status);

        $this->actingAs($admin)->postJson("/dashboard/facility-bookings/{$booking->id}/cancel")->assertOk();
        $this->assertSame($admin->id, $booking->fresh()->cancelled_by_user_id);
        $this->assertStringContainsString('cancelled', InAppNotification::where('user_id', $owner->id)->latest('id')->first()->body);
    }

    public function test_bookings_are_validated(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $gate = Landmark::create(['community_id' => $this->pool->community_id, 'name' => 'Main Gate', 'category' => 'Security Gate', 'lat' => 18.0, 'lng' => -76.8]);

        $this->book($resident, ['booked_on' => now()->subDay()->toDateString()])->assertJsonValidationErrors('booked_on');
        $this->book($resident, ['slot' => 'midnight'])->assertJsonValidationErrors('slot');
        $this->book($resident, ['guests' => 21])->assertJsonValidationErrors('guests');
        $this->book($resident, ['landmark_id' => $gate->id])->assertJsonValidationErrors('landmark_id');

        $this->assertSame(0, FacilityBooking::count());
    }

    public function test_security_staff_cannot_book(): void
    {
        $guard = User::factory()->role(UserRole::Security)->create();

        $this->book($guard)->assertForbidden();
        $this->actingAs($guard)->getJson('/dashboard/facility-bookings')->assertForbidden();
    }

    public function test_availability_shows_taken_slots_without_who_and_only_my_bookings(): void
    {
        $owner = User::factory()->role(UserRole::Homeowner)->create();
        $neighbour = User::factory()->role(UserRole::Homeowner)->create();
        $this->book($owner)->assertCreated();
        $date = now()->addDays(2)->toDateString();

        $this->actingAs($neighbour)->getJson("/dashboard/facility-bookings?landmark_id={$this->pool->id}&date={$date}")
            ->assertOk()
            ->assertJsonPath('takenSlots', ['afternoon'])
            ->assertJsonPath('guestLimit', 20)
            ->assertJsonPath('feeFormatted', 'Free')
            ->assertJsonPath('myBookings', []);

        $this->actingAs($owner)->getJson('/dashboard/facility-bookings')
            ->assertJsonPath('myBookings.0.facility', 'Pool Pavilion')
            ->assertJsonPath('myBookings.0.slotLabel', '12:00–16:00')
            ->assertJsonPath('myBookings.0.feeFormatted', 'Free');
    }

    public function test_booking_a_facility_with_a_fee_creates_an_invoice(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $clubhouse = Landmark::create([
            'community_id' => $this->pool->community_id,
            'name' => 'Grand Clubhouse',
            'category' => 'Community Center',
            'lat' => 18.0,
            'lng' => -76.8,
            'bookable' => true,
            'capacity' => 100,
            'fee_minor' => 7500, // $75.00
        ]);

        $response = $this->actingAs($resident)->postJson('/dashboard/facility-bookings', [
            'landmark_id' => $clubhouse->id,
            'booked_on' => now()->addDays(5)->toDateString(),
            'slot' => 'evening',
            'guests' => 30,
        ])->assertCreated()
            ->assertJsonPath('fee_minor', 7500)
            ->assertJsonPath('fee_formatted', '$75.00');

        $booking = FacilityBooking::where('landmark_id', $clubhouse->id)->sole();
        $this->assertSame(7500, $booking->fee_minor);
        $this->assertNotNull($booking->invoice_id);

        $invoice = Invoice::find($booking->invoice_id);
        $this->assertSame(7500, $invoice->amount_minor);
        $this->assertSame('Unpaid', $invoice->status);
        $this->assertSame($response->json('invoice_reference'), $invoice->reference);

        $item = $invoice->items()->sole();
        $this->assertSame('facility_booking', $item->category);
        $this->assertSame(7500, $item->amount_minor);
        $this->assertSame('Unpaid', $item->status);
    }

    public function test_cancelling_a_booking_cancels_its_unpaid_invoice(): void
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create();
        $clubhouse = Landmark::create([
            'community_id' => $this->pool->community_id,
            'name' => 'Grand Clubhouse',
            'category' => 'Community Center',
            'lat' => 18.0,
            'lng' => -76.8,
            'bookable' => true,
            'capacity' => 100,
            'fee_minor' => 5000,
        ]);

        $this->actingAs($resident)->postJson('/dashboard/facility-bookings', [
            'landmark_id' => $clubhouse->id,
            'booked_on' => now()->addDays(5)->toDateString(),
            'slot' => 'evening',
            'guests' => 10,
        ])->assertCreated();

        $booking = FacilityBooking::where('landmark_id', $clubhouse->id)->sole();
        $invoice = $booking->invoice;
        $this->assertSame('Unpaid', $invoice->status);

        $this->actingAs($resident)->postJson("/dashboard/facility-bookings/{$booking->id}/cancel")->assertOk();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('Cancelled', $invoice->fresh()->status);
        $this->assertSame('Cancelled', $invoice->items()->sole()->status);
    }

    public function test_scheduled_command_reminds_residents_24_hours_before_booking(): void
    {
        $tomorrowResident = User::factory()->role(UserRole::Homeowner)->create();
        $laterResident = User::factory()->role(UserRole::Homeowner)->create();
        $alreadyRemindedResident = User::factory()->role(UserRole::Homeowner)->create();

        // 1. Booking tomorrow - should be reminded
        $bookingTomorrow = FacilityBooking::create([
            'reference' => 'BK-TOMORROW',
            'landmark_id' => $this->pool->id,
            'user_id' => $tomorrowResident->id,
            'booked_on' => today()->addDay()->toDateString(),
            'slot' => 'morning',
            'guests' => 4,
            'status' => 'confirmed',
        ]);

        // 2. Booking in 3 days - should NOT be reminded yet
        FacilityBooking::create([
            'reference' => 'BK-LATER',
            'landmark_id' => $this->pool->id,
            'user_id' => $laterResident->id,
            'booked_on' => today()->addDays(3)->toDateString(),
            'slot' => 'afternoon',
            'guests' => 4,
            'status' => 'confirmed',
        ]);

        // 3. Booking tomorrow but already reminded - should NOT be reminded again
        FacilityBooking::create([
            'reference' => 'BK-ALREADY-REMINDED',
            'landmark_id' => $this->pool->id,
            'user_id' => $alreadyRemindedResident->id,
            'booked_on' => today()->addDay()->toDateString(),
            'slot' => 'evening',
            'guests' => 4,
            'status' => 'confirmed',
            'reminded_at' => now()->subHour(),
        ]);

        // Run dry-run first
        $this->artisan('facilities:send-booking-reminders', ['--dry-run' => true])
            ->expectsOutputToContain('1 reservation reminder(s) would be queued.')
            ->assertSuccessful();

        $this->assertNull($bookingTomorrow->fresh()->reminded_at);

        // Run real command
        $this->artisan('facilities:send-booking-reminders')
            ->expectsOutputToContain('1 facility booking reminder(s) queued.')
            ->assertSuccessful();

        $this->assertNotNull($bookingTomorrow->fresh()->reminded_at);

        // Notification delivered to tomorrow's resident
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $tomorrowResident->id,
            'subject_type' => $bookingTomorrow->getMorphClass(),
            'subject_id' => $bookingTomorrow->id,
            'status' => 'sent',
        ]);

        // Running again sends 0 reminders
        $this->artisan('facilities:send-booking-reminders')
            ->expectsOutputToContain('0 facility booking reminder(s) queued.')
            ->assertSuccessful();
    }
}
