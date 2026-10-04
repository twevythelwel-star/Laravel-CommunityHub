<?php

namespace App\Livewire\Notifications;

use App\Models\InAppNotification;
use App\Models\NotificationDelivery;
use App\Services\Notifications\Universal\DTOs\NotificationMessage;
use App\Services\Notifications\Universal\DTOs\NotificationRecipient;
use App\Services\Notifications\Universal\UniversalNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UniversalNotificationHub extends Component
{
    use WithPagination;

    public string $activeTab = 'catalog'; // catalog, dispatch, inbox, deliveries

    // Dispatch form state
    public array $selectedChannels = ['in_app', 'email'];

    public string $recipientName = 'Jane Resident';

    public string $recipientEmail = 'jane.resident@example.com';

    public string $recipientPhone = '+18765550144';

    public string $webhookUrl = 'https://api.example.com/webhooks/listener';

    public string $title = 'Community Security Notice';

    public string $body = 'Routine maintenance on North Gate perimeter sensors is scheduled for tomorrow at 08:00 AM.';

    public string $actionUrl = 'https://communityhub.io/security/updates';

    public string $priority = 'normal';

    public string $category = 'security';

    // Provider switching
    public array $channelProviders = [];

    // Dispatch feedback
    public ?array $lastDispatchResult = null;

    public ?string $feedbackMessage = null;

    public string $feedbackType = 'success';

    public function mount(UniversalNotificationService $service): void
    {
        $catalog = $service->getChannelsCatalog();
        foreach ($catalog as $key => $item) {
            $this->channelProviders[$key] = $item['active_provider'];
        }
    }

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function toggleChannel(string $channel): void
    {
        if (in_array($channel, $this->selectedChannels, true)) {
            $this->selectedChannels = array_values(array_diff($this->selectedChannels, [$channel]));
        } else {
            $this->selectedChannels[] = $channel;
        }
    }

    public function selectAllChannels(): void
    {
        $this->selectedChannels = [
            'email', 'sms', 'whatsapp', 'push', 'slack', 'teams', 'webhook', 'database', 'in_app',
        ];
    }

    public function updateProvider(string $channelKey, string $newProvider, UniversalNotificationService $service): void
    {
        $this->authorize('broadcastNotices');

        $this->channelProviders[$channelKey] = $newProvider;
        $service->setChannelProvider($channelKey, $newProvider);
        $this->feedbackMessage = "Active provider for [{$channelKey}] updated to [{$newProvider}].";
        $this->feedbackType = 'success';
    }

    /**
     * Administrators only (`broadcastNotices`), as on the API: this sends to
     * any email address, phone number or webhook URL typed into the form.
     */
    public function triggerDispatch(UniversalNotificationService $service): void
    {
        $this->authorize('broadcastNotices');

        $this->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'selectedChannels' => 'required|array|min:1',
        ]);

        $currentUser = Auth::user();

        $recipient = new NotificationRecipient(
            userId: $currentUser?->id,
            name: $this->recipientName,
            email: $this->recipientEmail,
            phone: $this->recipientPhone,
            deviceTokens: ['fcm_token_demo_device_9821'],
            webhookUrl: $this->webhookUrl,
            slackWebhookUrl: 'https://hooks.slack.com/services/demo/hub',
            teamsWebhookUrl: 'https://outlook.office.com/webhook/demo',
            tenantId: $currentUser?->tenant_id ?? 'solaris-bay'
        );

        $message = new NotificationMessage(
            title: $this->title,
            body: $this->body,
            actionUrl: $this->actionUrl,
            priority: $this->priority,
            category: $this->category,
            data: ['origin' => 'UniversalNotificationHub', 'dispatched_by' => $currentUser?->email]
        );

        $summary = $service->dispatch($recipient, $message, $this->selectedChannels);
        $this->lastDispatchResult = $summary->toArray();

        $this->feedbackMessage = 'Notification successfully dispatched across '.count($summary->successfulChannels()).' channel(s)!';
        $this->feedbackType = 'success';
    }

    public function markAsRead(int $id): void
    {
        $notif = InAppNotification::query()->where('user_id', Auth::id())->find($id);
        if ($notif) {
            $notif->markAsRead();
            $this->feedbackMessage = 'Notification marked as read.';
            $this->feedbackType = 'success';
        }
    }

    public function markAllAsRead(): void
    {
        $userId = Auth::id();
        $query = InAppNotification::query()->unread();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $count = $query->update(['read_at' => now()]);

        $this->feedbackMessage = "Marked {$count} in-app notifications as read.";
        $this->feedbackType = 'success';
    }

    public function render(UniversalNotificationService $service): View
    {
        $catalog = $service->getChannelsCatalog();

        $userId = Auth::id();
        $inboxQuery = InAppNotification::query();
        if ($userId) {
            $inboxQuery->where('user_id', $userId);
        }
        $unreadCount = (clone $inboxQuery)->unread()->count();
        $inboxItems = $inboxQuery->latest()->paginate(10, ['*'], 'inboxPage');

        // Every recipient's address and message: one's own, unless one sends to everyone.
        $deliveries = NotificationDelivery::query()
            ->when(! Auth::user()?->can('broadcastNotices'), fn ($q) => $q->where('user_id', Auth::id()))
            ->latest()
            ->paginate(10, ['*'], 'deliveriesPage');

        return view('livewire.notifications.universal-notification-hub', [
            'catalog' => $catalog,
            'unreadCount' => $unreadCount,
            'inboxItems' => $inboxItems,
            'deliveries' => $deliveries,
        ])->layout('layouts.app');
    }
}
