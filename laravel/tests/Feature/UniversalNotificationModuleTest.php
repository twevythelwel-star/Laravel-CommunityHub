<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Notifications\UniversalNotificationHub;
use App\Models\InAppNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\UniversalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UniversalNotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_catalog_discovers_all_nine_channels_and_providers(): void
    {
        $service = app(UniversalNotificationService::class);
        $catalog = $service->getChannelsCatalog();

        $this->assertCount(9, $catalog);
        $this->assertArrayHasKey('email', $catalog);
        $this->assertArrayHasKey('sms', $catalog);
        $this->assertArrayHasKey('whatsapp', $catalog);
        $this->assertArrayHasKey('push', $catalog);
        $this->assertArrayHasKey('slack', $catalog);
        $this->assertArrayHasKey('teams', $catalog);
        $this->assertArrayHasKey('webhook', $catalog);
        $this->assertArrayHasKey('database', $catalog);
        $this->assertArrayHasKey('in_app', $catalog);

        $this->assertSame('operational', $catalog['email']['status']);
        $this->assertSame('operational', $catalog['sms']['status']);
        $this->assertSame('operational', $catalog['whatsapp']['status']);
        $this->assertSame('operational', $catalog['push']['status']);
        $this->assertSame('operational', $catalog['in_app']['status']);
    }

    public function test_provider_swapping_dynamically_at_runtime(): void
    {
        $facade = app(NotificationService::class);

        // Initial default email provider is mailgun
        $this->assertSame('mailgun', $facade->channel('email')->activeProviderName());

        // Switch email provider to sendgrid
        $facade->setChannelProvider('email', 'sendgrid');
        $this->assertSame('sendgrid', $facade->channel('email')->activeProviderName());

        // Switch email provider to postmark
        $facade->setChannelProvider('email', 'postmark');
        $this->assertSame('postmark', $facade->channel('email')->activeProviderName());

        // Switch push provider to onesignal
        $facade->setChannelProvider('push', 'onesignal');
        $this->assertSame('onesignal', $facade->channel('push')->activeProviderName());
    }

    public function test_universal_notification_dispatches_across_multiple_channels(): void
    {
        $service = app(UniversalNotificationService::class);
        $user = User::factory()->create([
            'email' => 'resident@example.com',
            'phone' => '+18765550199',
            'role' => UserRole::Homeowner,
        ]);

        $recipient = NotificationRecipient::fromUser($user);
        $recipient->deviceTokens = ['device_token_abc_123'];
        $recipient->webhookUrl = 'https://webhook.site/mock-listener';
        $recipient->slackWebhookUrl = 'https://hooks.slack.com/services/mock/T00/B00/X00';
        $recipient->teamsWebhookUrl = 'https://outlook.office.com/webhook/mock';

        $message = new NotificationMessage(
            title: 'Water Service Advisory',
            body: 'Scheduled system pressure maintenance between 02:00 and 04:00 AM.',
            actionUrl: 'https://communityhub.io/notices/water-101',
            priority: 'high',
            category: 'system'
        );

        $channels = ['in_app', 'database', 'email', 'sms', 'whatsapp', 'push', 'slack', 'teams', 'webhook'];
        $summary = $service->dispatch($recipient, $message, $channels);

        $this->assertNotNull($summary->trackingId);
        $this->assertTrue($summary->hasAnySuccess());
        $this->assertSame(9, count($summary->reports));

        foreach ($channels as $channel) {
            $this->assertTrue($summary->isDelivered($channel), "Expected {$channel} to be delivered");
        }

        // Verify database audit log
        $deliveries = NotificationDelivery::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(1, $deliveries->count());
    }

    public function test_in_app_notifications_inbox_and_read_lifecycle(): void
    {
        $user = User::factory()->create([
            'email' => 'resident2@example.com',
            'role' => UserRole::Homeowner,
        ]);

        $service = app(NotificationService::class);

        $this->assertSame(0, $user->unreadInAppNotificationsCount());

        // Dispatch 2 notifications
        $service->send($user, 'First Notice', 'Welcome to the estate portal.', ['in_app']);
        $service->send($user, 'Second Notice', 'Your gate pass is expiring.', ['in_app']);

        $this->assertSame(2, $user->unreadInAppNotificationsCount());

        $firstNotification = InAppNotification::where('user_id', $user->id)
            ->where('title', 'First Notice')
            ->first();

        $this->assertNotNull($firstNotification);
        $this->assertFalse($firstNotification->isRead());

        // Mark first notification as read
        $firstNotification->markAsRead();
        $this->assertTrue($firstNotification->fresh()->isRead());
        $this->assertSame(1, $user->unreadInAppNotificationsCount());

        // Mark as unread
        $firstNotification->markAsUnread();
        $this->assertSame(2, $user->unreadInAppNotificationsCount());
    }

    public function test_outbound_signed_webhook_channel_generates_hmac_signature(): void
    {
        $service = app(UniversalNotificationService::class);
        $recipient = new NotificationRecipient(
            userId: 999,
            name: 'External Integration',
            webhookUrl: 'https://example.com/api/webhooks/incoming'
        );

        $message = new NotificationMessage(
            title: 'Gate Pass Scanned',
            body: 'Visitor John Doe checked in at Main Gate',
            category: 'pass'
        );

        $report = $service->channel('webhook')->send($recipient, $message);

        $this->assertSame('delivered', $report->status);
        $this->assertNotNull($report->messageId);
        $this->assertArrayHasKey('signature_v1', $report->metadata);
        $this->assertNotEmpty($report->metadata['signature_v1']);
    }

    public function test_universal_notifications_api_endpoints(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // 1. Channels catalog
        $catalogRes = $this->getJson('/api/v1/notifications/channels');
        $catalogRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email.key', 'email')
            ->assertJsonPath('data.in_app.key', 'in_app');

        // 2. Dispatch notification API
        $dispatchRes = $this->actingAs($admin)->postJson('/api/v1/notifications/dispatch', [
            'user_id' => $admin->id,
            'title' => 'API System Broadcast',
            'body' => 'High priority alert sent via API endpoint.',
            'priority' => 'urgent',
            'category' => 'security',
            'channels' => ['in_app', 'email', 'database'],
        ]);

        $dispatchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message.priority', 'urgent');

        $this->assertNotNull($dispatchRes->json('data.tracking_id'));

        // 3. User in-app inbox
        $inboxRes = $this->actingAs($admin)->getJson('/api/v1/notifications/inbox');
        $inboxRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $inboxRes->json('unread_count'));

        $firstItem = $inboxRes->json('data.0');
        $this->assertNotNull($firstItem);
        $notificationId = $firstItem['id'];

        // 4. Mark notification as read
        $readRes = $this->actingAs($admin)->postJson("/api/v1/notifications/{$notificationId}/read");
        $readRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $notificationId);

        // 5. Mark all as read
        $readAllRes = $this->actingAs($admin)->postJson('/api/v1/notifications/read-all');
        $readAllRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 6. Delivery telemetry logs
        $delivRes = $this->actingAs($admin)->getJson('/api/v1/notifications/deliveries');
        $delivRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_livewire_universal_notification_hub_renders_and_dispatches(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'name' => 'Admin Officer',
            'email' => 'admin.hub@example.com',
        ]);

        Livewire::actingAs($admin)
            ->test(UniversalNotificationHub::class)
            ->assertSee('Notification Command Center')
            ->assertSee('Supported Notification Channels')
            ->call('selectTab', 'catalog')
            ->call('updateProvider', 'email', 'sendgrid')
            ->assertSee('Active provider for [email] updated to [sendgrid].')
            ->call('selectTab', 'dispatch')
            ->assertSee('Multi-Channel Dispatcher')
            ->call('triggerDispatch')
            ->assertSee('Notification successfully dispatched across')
            ->call('selectTab', 'inbox')
            ->assertSee('Resident In-App Notification Feed')
            ->call('markAllAsRead')
            ->call('selectTab', 'deliveries')
            ->assertSee('Delivery Audit Logs');
    }
}
