<?php

namespace App\Events;

use App\Models\Warning;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SecurityAlertBroadcastEvent implements ShouldBroadcast
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

    public function broadcastAs(): string
    {
        return 'security.alert';
    }
}
