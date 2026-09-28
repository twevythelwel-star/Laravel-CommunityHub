<?php

namespace App\Events;

use App\Models\Warning;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// ShouldRescue: if Reverb is unreachable the failure is reported, never
// thrown into the request (a check-in or an alert) that fired the event.
class SecurityAlertBroadcastEvent implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(public Warning $warning)
    {
        $this->payload = [
            'id' => $warning->id,
            'title' => $warning->title,
            'description' => $warning->description,
            'severity' => $warning->severity ?? 'high',
            'created_at' => $warning->created_at->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            // Private: on a public channel anyone with the app's websocket key,
            // resident or not, would receive the estate's security alerts.
            new PrivateChannel('community-alerts'),
        ];
    }

    /**
     * Only the summary above, not every public property of the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'security.alert';
    }
}
