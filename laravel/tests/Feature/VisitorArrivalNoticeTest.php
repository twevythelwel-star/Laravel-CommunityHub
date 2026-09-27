<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\VisitorCheckedInEvent;
use App\Models\Notification;
use App\Models\ResidentMessage;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Visitor;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * A visitor's arrival is the host's business. It goes to the host alone, by
 * the host's own email/SMS settings, and never to the community notice board
 * or a public broadcast channel.
 */
class VisitorArrivalNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const TWILIO_URL = 'https://api.twilio.com/2010-04-01/Accounts/AC_test/Messages.json';

    private function host(array $preferences = []): User
    {
        $host = User::factory()->role(UserRole::Homeowner)->create([
            'email' => 'host@residence.test',
            'phone' => '+18765550108',
        ]);

        if ($preferences !== []) {
            UserPreference::create(['user_id' => $host->id, ...$preferences]);
        }

        return $host;
    }

    private function visitorOf(User $host, array $overrides = []): Visitor
    {
        return Visitor::create([
            'name' => 'Liam Johnson',
            'type' => 'One-time',
            'status' => 'Checked In',
            'expected_at' => now(),
            'homeowner_id' => $host->id,
            'homeowner_name' => $host->name,
            'vehicle' => 'Blue Toyota 1234AB',
            'contact' => 'liam@visitor.test',
            'notify_email' => false,
            'notify_sms' => false,
            'notify_whatsapp' => false,
            'checked_in_at' => now(),
            ...$overrides,
        ]);
    }

    /** @return array<int, Email> */
    private function sentEmails(): array
    {
        return array_map(
            fn ($sent) => $sent->getOriginalMessage(),
            iterator_to_array(app('mailer')->getSymfonyTransport()->messages())
        );
    }

    private function arrive(Visitor $visitor): void
    {
        event(new VisitorCheckedInEvent($visitor, 'Main Gate'));
    }

    public function test_an_arrival_is_never_posted_to_the_community_notice_board(): void
    {
        $this->arrive($this->visitorOf($this->host()));

        $this->assertSame(0, Notification::count());
    }

    public function test_the_host_is_emailed_by_default_and_no_one_else(): void
    {
        $host = $this->host();
        $this->arrive($this->visitorOf($host));

        $message = $host->inboxMessages()->sole();
        $this->assertSame('visitor_checked_in', $message->kind);
        $this->assertStringContainsString('Blue Toyota 1234AB', $message->body);
        $this->assertSame(1, ResidentMessage::count(), 'only the host gets it');

        $emails = $this->sentEmails();
        $this->assertCount(1, $emails);
        $this->assertSame('host@residence.test', $emails[0]->getTo()[0]->getAddress());
        $this->assertStringContainsString('Liam Johnson', $emails[0]->getSubject());
        $this->assertStringContainsString('Blue Toyota 1234AB', $emails[0]->getTextBody());
    }

    public function test_sms_goes_to_the_phone_and_email_to_the_address(): void
    {
        config(['services.twilio.sid' => 'AC_test', 'services.twilio.token' => 'secret-token', 'services.twilio.from' => '+18765550000']);
        Http::fake([self::TWILIO_URL => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201)]);

        $this->arrive($this->visitorOf($this->host(['notify_email' => true, 'notify_sms' => true])));

        Http::assertSent(fn (Request $r) => $r['To'] === '+18765550108');
        $this->assertSame('host@residence.test', $this->sentEmails()[0]->getTo()[0]->getAddress());
    }

    public function test_the_hosts_own_settings_decide_not_the_visitors_pass_settings(): void
    {
        Http::fake();
        // The visitor asked for their pass by SMS and email; the host turned both off.
        $visitor = $this->visitorOf($this->host(['notify_email' => false, 'notify_sms' => false]), [
            'notify_email' => true,
            'notify_sms' => true,
        ]);

        $this->arrive($visitor);

        $this->assertSame([], $this->sentEmails());
        Http::assertNothingSent();
    }

    public function test_the_live_arrival_feed_is_private(): void
    {
        $channels = (new VisitorCheckedInEvent($this->visitorOf($this->host())))->broadcastOn();

        foreach ($channels as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel, "{$channel->name} is public");
        }
    }

    public function test_the_cleanup_removes_leaked_arrival_notices_and_nothing_an_administrator_wrote(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leaked = Notification::create([
            'title' => 'Visitor Arrived: Liam Johnson',
            'content' => 'Liam Johnson has arrived and checked in at Main Gate (Vehicle: Blue Toyota).',
            'author_id' => null,
            'author_name' => 'Community Hub Management',
            'published_at' => now(),
        ]);
        $written = Notification::create([
            'title' => 'Visitor Arrived: new gate procedure',
            'content' => 'From Monday visitors sign in at the kiosk.',
            'author_id' => $admin->id,
            'author_name' => $admin->display_name,
            'published_at' => now(),
        ]);

        (require database_path('migrations/2026_09_27_123352_remove_leaked_visitor_arrival_notices.php'))->up();

        $this->assertModelMissing($leaked);
        $this->assertModelExists($written);
    }
}
