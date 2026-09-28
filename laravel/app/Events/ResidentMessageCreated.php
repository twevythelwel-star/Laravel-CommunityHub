<?php

namespace App\Events;

use App\Models\ResidentMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message landed in one resident's inbox. Sent to that resident's own
 * private channel so the Inbox badge updates without a page load.
 */
// ShouldRescue: if Reverb is unreachable the failure is reported, never
// thrown into the request (a check-in or an alert) that fired the event.
class ResidentMessageCreated implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ResidentMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->message->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'inbox.message';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'kind' => $this->message->kind,
            'title' => $this->message->title,
            'unread' => $this->message->user->inboxMessages()->unread()->count(),
        ];
    }
}
