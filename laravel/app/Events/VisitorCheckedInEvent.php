<?php

namespace App\Events;

use App\Models\Visitor;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// ShouldRescue: if Reverb is unreachable the failure is reported, never
// thrown into the request (a check-in or an alert) that fired the event.
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
            // Private: a public channel would give every arrival (visitor,
            // vehicle, host) to anyone holding the app's websocket key.
            new PrivateChannel('gatehouse-stream'),
        ];
    }

    /**
     * Only the summary above. Without this Laravel sends every public
     * property, which here includes the whole Visitor record: ID number,
     * contact details and the share token that opens their guest pass.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'visitor.checked-in';
    }
}
