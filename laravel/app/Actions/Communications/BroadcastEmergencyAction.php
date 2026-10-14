<?php

namespace App\Actions\Communications;

use App\Events\Realtime\LiveNotificationEvent;
use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communications\EmergencyAudience;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Send an emergency, advisory or general notice to an audience, on the
 * channels the sender chose: in-app, realtime push, SMS, WhatsApp and email.
 * The notice is also stored for the notices page. A failed send on one
 * channel or to one recipient is logged and skipped, never fatal.
 */
class BroadcastEmergencyAction
{
    private const ACTION_URL = '/dashboard/occupancy';

    public function __construct(
        private readonly EmergencyAudience $audience,
        private readonly SmsService $sms,
        private readonly WhatsAppService $whatsapp,
    ) {}

    /**
     * @param  array{title: string, content: string, severity: string, audience: string, target_building?: string|null, target_property?: string|null, send_push?: bool|null, send_sms?: bool|null, send_whatsapp?: bool|null, send_email?: bool|null, send_in_app?: bool|null}  $broadcast
     * @return array{in_app: int, sms: int, whatsapp: int, email: int, push: int}
     */
    public function execute(User $sender, array $broadcast): array
    {
        $audience = $broadcast['audience'];
        $property = $broadcast['target_property'] ?? null;
        $title = $this->prefix($broadcast['severity']).$broadcast['title'];
        $content = $broadcast['content'];
        $priority = strtoupper($broadcast['severity']) === 'EMERGENCY' ? 'urgent' : 'high';

        $users = $this->audience->users($audience, $broadcast['target_building'] ?? null, $property);

        Notification::create([
            'title' => $title,
            'content' => $content,
            'target_roles' => $this->audience->noticeRoles($audience),
            'author_id' => $sender->id,
            'author_name' => $sender->display_name,
            'published_at' => now(),
        ]);

        $phones = $this->audience->phones($audience, $users, $property);
        $text = "{$title}: {$content}";

        // In-app and push first: they reach screens at once, the rest is slower.
        $inApp = ($broadcast['send_in_app'] ?? true) ? $this->inApp($users, $title, $content, $priority) : 0;
        $push = ($broadcast['send_push'] ?? true) ? $this->push($users, $title, $content, $priority) : 0;

        $sent = [
            'in_app' => $inApp,
            'sms' => ($broadcast['send_sms'] ?? false) && $this->sms->isConfigured()
                ? $this->each($phones, fn (string $phone) => $this->sms->send($phone, $text), 'SMS') : 0,
            'whatsapp' => ($broadcast['send_whatsapp'] ?? false) && $this->whatsapp->isConfigured()
                ? $this->each($phones, fn (string $phone) => $this->whatsapp->send($phone, $text), 'WhatsApp') : 0,
            'email' => ($broadcast['send_email'] ?? false)
                ? $this->each($users->pluck('email')->filter()->unique(), fn (string $email) => Mail::raw($text, fn ($m) => $m->to($email)->subject($title)), 'Email') : 0,
            'push' => $push,
        ];

        $sender->recordActivity(sprintf(
            "Dispatched %s broadcast to audience '%s': %s (%d SMS, %d WhatsApp, %d Email)",
            strtoupper($broadcast['severity']), $audience, $broadcast['title'], $sent['sms'], $sent['whatsapp'], $sent['email'],
        ));

        return $sent;
    }

    private function prefix(string $severity): string
    {
        return match ($severity) {
            'emergency' => '🚨 [EMERGENCY ALERT] ',
            'advisory' => '⚠️ [ADVISORY] ',
            default => '📢 [COMMUNITY NOTICE] ',
        };
    }

    /** @param  Collection<int, User>  $users */
    private function inApp(Collection $users, string $title, string $content, string $priority): int
    {
        foreach ($users as $user) {
            InAppNotification::create([
                'user_id' => $user->id,
                'category' => 'emergency',
                'title' => $title,
                'body' => $content,
                'priority' => $priority,
                'action_url' => self::ACTION_URL,
            ]);
        }

        return $users->count();
    }

    /**
     * Realtime push; a broadcast driver that is down must not stop the alert.
     *
     * @param  Collection<int, User>  $users
     */
    private function push(Collection $users, string $title, string $content, string $priority): int
    {
        $sent = 0;
        foreach ($users as $user) {
            try {
                LiveNotificationEvent::dispatch($user->id, $title, $content, self::ACTION_URL, $priority, 'emergency');
                $sent++;
            } catch (Throwable) {
                // Delivery by the other channels goes on.
            }
        }

        return $sent;
    }

    /**
     * Send to each recipient, counting successes and logging failures.
     *
     * @param  Collection<int, string>  $recipients
     */
    private function each(Collection $recipients, callable $send, string $channel): int
    {
        $sent = 0;
        foreach ($recipients as $recipient) {
            try {
                $send((string) $recipient);
                $sent++;
            } catch (Throwable $e) {
                Log::warning("Failed to dispatch emergency {$channel} to {$recipient}: ".$e->getMessage());
            }
        }

        return $sent;
    }
}
