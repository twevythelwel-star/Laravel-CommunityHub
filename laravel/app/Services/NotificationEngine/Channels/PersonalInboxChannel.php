<?php

namespace App\Services\NotificationEngine\Channels;

use App\Events\ResidentMessageCreated;
use App\Models\ResidentMessage;
use App\Services\NotificationEngine\ChannelDispatchResult;
use App\Services\NotificationEngine\Contracts\NotificationChannelInterface;
use App\Services\NotificationEngine\NotificationPayload;
use Throwable;

/**
 * Delivers to one resident's personal in-app inbox (resident_messages).
 *
 * The counterpart to CommunityNoticeChannel: that one needs an audience and
 * refuses a person; this one needs a person ($payload->user) and refuses an
 * audience, so neither can be used for the other's job.
 */
class PersonalInboxChannel implements NotificationChannelInterface
{
    public function name(): string
    {
        return 'inbox';
    }

    public function label(): string
    {
        return 'Personal Inbox';
    }

    public function provider(): string
    {
        return 'Local Database';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(NotificationPayload $payload): ChannelDispatchResult
    {
        if (! $payload->user) {
            return ChannelDispatchResult::failed($this->name(), 'An inbox message needs the user it is for.');
        }

        if ($payload->toEveryone || filled($payload->targetRoles)) {
            return ChannelDispatchResult::failed(
                $this->name(),
                'An inbox message is for one person; use the community notice board for an audience.'
            );
        }

        try {
            $message = ResidentMessage::create([
                'user_id' => $payload->user->id,
                'kind' => (string) ($payload->metadata['notice_type'] ?? $payload->metadata['event'] ?? 'general'),
                'title' => $payload->title,
                'body' => $payload->body,
                'action_url' => $payload->actionUrl,
            ]);

            $this->announce($message);

            return ChannelDispatchResult::sent($this->name(), (string) $message->id, ['resident_message_id' => $message->id]);
        } catch (Throwable $e) {
            report($e);

            return ChannelDispatchResult::failed($this->name(), 'Inbox message could not be saved: '.$e->getMessage());
        }
    }

    /**
     * Tell the resident's open browser tabs, if live updates are running.
     * The message is already saved, so a websocket failure must not undo it
     * or report the delivery as failed.
     */
    private function announce(ResidentMessage $message): void
    {
        try {
            event(new ResidentMessageCreated($message));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
