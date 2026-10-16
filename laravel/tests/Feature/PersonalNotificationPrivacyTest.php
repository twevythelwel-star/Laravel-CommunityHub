<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendInvoicePaymentReminderJob;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\User;
use App\Models\Visitor;
use App\Services\NotificationEngine\NotificationEngine;
use App\Services\NotificationEngine\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A message meant for one resident goes to that resident's own notifications,
 * never onto the community notice board.
 */
class PersonalNotificationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_payment_reminder_reaches_only_the_homeowner(): void
    {
        $homeowner = User::factory()->role(UserRole::Homeowner)->create(['name' => 'Owen Debtor', 'lot' => '42']);
        $neighbour = User::factory()->role(UserRole::Homeowner)->create();
        $invoice = Invoice::create([
            'user_id' => $homeowner->id, 'reference' => 'INV-PRIV-1', 'amount_minor' => 1500000, 'currency' => 'JMD',
            'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
            'due_on' => now()->subDays(5), 'status' => 'Overdue',
        ]);

        (new SendInvoicePaymentReminderJob($invoice, ['in_app']))->handle(app(NotificationEngine::class));

        $this->assertSame(0, Notification::count(), 'no notice-board announcement is created');
        $reminder = InAppNotification::where('user_id', $homeowner->id)->sole();
        $this->assertStringContainsString('INV-PRIV-1', $reminder->body);
        $this->assertSame(0, InAppNotification::where('user_id', $neighbour->id)->count());

        $this->actingAs($neighbour)->get('/dashboard/notifications')
            ->assertOk()
            ->assertDontSee('INV-PRIV-1')
            ->assertDontSee('Owen Debtor');
    }

    public function test_a_visitor_arrival_reaches_only_the_host(): void
    {
        $host = User::factory()->role(UserRole::Homeowner)->create();
        $neighbour = User::factory()->role(UserRole::Homeowner)->create();
        $visitor = Visitor::factory()->create([
            'homeowner_id' => $host->id, 'name' => 'Vera Visitor', 'vehicle' => 'PL 1234',
            'notify_sms' => false, 'notify_whatsapp' => false, 'notify_email' => false,
        ]);

        $outcomes = app(NotificationEngine::class)->dispatchArrivalNotice($visitor, 'Main Gate');

        $this->assertSame('sent', $outcomes['in_app']->status);
        $this->assertSame(0, Notification::count(), 'no notice-board announcement is created');
        $this->assertStringContainsString('Vera Visitor', InAppNotification::where('user_id', $host->id)->sole()->title);
        $this->assertSame(0, InAppNotification::where('user_id', $neighbour->id)->count());
    }

    public function test_an_in_app_message_with_no_addressee_is_refused_rather_than_published(): void
    {
        $outcomes = app(NotificationEngine::class)->send(['in_app'], new NotificationPayload(title: 'Private', body: 'For someone'));

        $this->assertSame('failed', $outcomes['in_app']->status);
        $this->assertSame(0, Notification::count());
        $this->assertSame(0, InAppNotification::count());
    }

    public function test_a_broadcast_still_publishes_to_the_notice_board(): void
    {
        $outcomes = app(NotificationEngine::class)->broadcast('Pool closed', 'The pool is closed today.', ['Homeowner']);

        $this->assertSame('sent', $outcomes['in_app']->status);
        $this->assertSame('Pool closed', Notification::sole()->title);
    }
}
