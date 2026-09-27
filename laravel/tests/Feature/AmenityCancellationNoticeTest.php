<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * When someone other than the resident cancels their amenity booking, the
 * resident is told by email/SMS per their preferences, the canceller is told
 * whether that went out, and nothing is posted to the community notice board.
 */
class AmenityCancellationNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const TWILIO_URL = 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json';

    private function resident(array $preferences = []): User
    {
        $resident = User::factory()->role(UserRole::Homeowner)->create([
            'email' => 'marcus@residence.test',
            'phone' => '+18765550103',
        ]);

        if ($preferences !== []) {
            UserPreference::create(['user_id' => $resident->id, ...$preferences]);
        }

        return $resident;
    }

    private function bookingFor(User $resident): AmenityBooking
    {
        return AmenityBooking::factory()->create([
            'user_id' => $resident->id,
            'amenity_id' => Amenity::factory()->create(['name' => 'Central Pool'])->id,
            'booked_on' => now()->addDays(3)->toDateString(),
            'slot' => 'afternoon',
        ]);
    }

    private function cancel(User $by, AmenityBooking $booking, ?string $reason = null): TestResponse
    {
        return $this->actingAs($by)->delete(route('dashboard.amenity-bookings.destroy', $booking), array_filter(['reason' => $reason]));
    }

    /** @return array<int, Email> */
    private function sentEmails(): array
    {
        return array_map(
            fn ($sent) => $sent->getOriginalMessage(),
            iterator_to_array(app('mailer')->getSymfonyTransport()->messages())
        );
    }

    private function configureTwilio(): void
    {
        config(['services.twilio.sid' => 'AC_test', 'services.twilio.token' => 'secret-token', 'services.twilio.from' => '+18765550000']);
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201)]);
    }

    public function test_an_administrator_cancelling_emails_the_resident_with_the_reason(): void
    {
        $resident = $this->resident();
        $booking = $this->bookingFor($resident);
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->cancel($admin, $booking, 'Pool closed for maintenance')
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'notified by email'));

        $emails = $this->sentEmails();
        $this->assertCount(1, $emails);
        $this->assertSame('marcus@residence.test', $emails[0]->getTo()[0]->getAddress());
        $this->assertSame('Amenity booking cancelled', $emails[0]->getSubject());
        $body = $emails[0]->getTextBody();
        $this->assertStringContainsString($booking->reference, $body);
        $this->assertStringContainsString('Central Pool', $body);
        $this->assertStringContainsString('12:00-16:00', $body);
        $this->assertStringContainsString('Reason: Pool closed for maintenance.', $body);

        $message = $resident->inboxMessages()->sole();
        $this->assertSame('amenity_booking_cancelled', $message->kind);
        $this->assertStringContainsString('Reason: Pool closed for maintenance.', $message->body);
        $this->assertSame('/dashboard/map', $message->action_url);

        $booking->refresh();
        $this->assertSame($admin->id, $booking->cancelled_by);
        $this->assertSame('Pool closed for maintenance', $booking->cancellation_reason);
    }

    public function test_a_resident_cancelling_their_own_booking_is_not_messaged(): void
    {
        $resident = $this->resident();

        $this->cancel($resident, $this->bookingFor($resident))
            ->assertSessionHas('success', fn (string $m) => ! str_contains($m, 'notified'));

        $this->assertSame([], $this->sentEmails());
        $this->assertSame(0, $resident->inboxMessages()->count());
    }

    public function test_a_resident_who_chose_sms_gets_a_text_at_their_phone_and_no_email(): void
    {
        $this->configureTwilio();
        $resident = $this->resident(['notify_email' => false, 'notify_sms' => true]);
        $booking = $this->bookingFor($resident);

        $this->cancel(User::factory()->role(UserRole::Admin)->create(), $booking)
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'notified by SMS'));

        Http::assertSent(fn (Request $r) => $r->url() === self::TWILIO_URL
            && $r['To'] === '+18765550103'
            && str_contains($r['Body'], $booking->reference));
        $this->assertSame([], $this->sentEmails());
    }

    public function test_the_administrator_is_told_when_sms_could_not_be_sent(): void
    {
        config(['services.twilio.sid' => null, 'services.twilio.token' => null, 'services.twilio.from' => null]);
        Http::fake();
        $resident = $this->resident(['notify_email' => false, 'notify_sms' => true]);

        $this->cancel(User::factory()->role(UserRole::Admin)->create(), $this->bookingFor($resident))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'SMS could not be sent') && str_contains($m, 'in their inbox'));

        Http::assertNothingSent();
    }

    public function test_the_administrator_is_told_when_the_resident_turned_notifications_off(): void
    {
        $resident = $this->resident(['notify_email' => false, 'notify_sms' => false]);

        $this->cancel(User::factory()->role(UserRole::Admin)->create(), $this->bookingFor($resident))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'email and SMS notifications turned off'));

        $this->assertSame([], $this->sentEmails());
        // With email and SMS off, the inbox is how they find out.
        $this->assertSame(1, $resident->inboxMessages()->count());
    }

    public function test_the_cancellation_is_never_posted_to_the_community_notice_board(): void
    {
        $this->cancel(User::factory()->role(UserRole::Admin)->create(), $this->bookingFor($this->resident()));

        $this->assertSame(0, Notification::count());
    }

    public function test_the_resident_sees_an_office_cancellation_and_its_reason_in_their_bookings(): void
    {
        $resident = $this->resident();
        $byOffice = $this->bookingFor($resident);
        $bySelf = AmenityBooking::factory()->create(['user_id' => $resident->id, 'booked_on' => now()->addDays(4)->toDateString()]);

        $this->cancel(User::factory()->role(UserRole::Admin)->create(), $byOffice, 'Maintenance');
        $this->cancel($resident, $bySelf);

        $this->actingAs($resident)->get('/dashboard/map')
            ->assertInertia(fn (Assert $page) => $page
                ->has('myBookings', 1)
                ->where('myBookings.0.reference', $byOffice->reference)
                ->where('myBookings.0.cancelledByOffice', true)
                ->where('myBookings.0.cancellationReason', 'Maintenance'));
    }

    public function test_the_reason_is_limited_in_length(): void
    {
        $booking = $this->bookingFor($this->resident());

        $this->cancel(User::factory()->role(UserRole::Admin)->create(), $booking, str_repeat('x', 256))
            ->assertSessionHasErrors('reason');

        $this->assertSame('confirmed', $booking->fresh()->status);
    }
}
