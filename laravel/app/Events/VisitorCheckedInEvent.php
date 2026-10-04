<?php

namespace App\Events;

use App\Models\Visitor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VisitorCheckedInEvent implements ShouldBroadcast, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $payload;

    public function __construct(public Visitor $visitor, public string $gate = 'Main Gate 1')
    {
        $this->payload = [
            'visitor_id' => $visitor->id,
            'visitor_name' => $visitor->name,
            'vehicle' => $visitor->vehicle,
            'entry_gate' => $gate,
            'homeowner_id' => $visitor->homeowner_id,
            'homeowner_name' => $visitor->homeowner_name,
            'checked_in_at' => $visitor->checked_in_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->visitor->homeowner_id}"),
            new Channel('gatehouse-stream'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'visitor.checked-in';
    }
}
