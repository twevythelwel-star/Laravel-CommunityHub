<?php

namespace App\Events;

use App\Models\Warning;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
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
            'id'          => $warning->id,
            'title'       => $warning->title,
            'description' => $warning->description,
            'severity'    => $warning->severity ?? 'high',
            'created_at'  => $warning->created_at->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('community-alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'security.alert';
    }
}
