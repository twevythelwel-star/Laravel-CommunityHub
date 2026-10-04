<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperationsCommandCenterEvent implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $alertId,
        public string $type, // gate_traffic, perimeter_sensor, staff_dispatch, emergency
        public string $severity, // info, warning, critical
        public string $headline,
        public string $location,
        public string $operatorName,
        public array $details = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('operations-center'),
            new Channel('community-alerts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operations.command-event';
    }

    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alertId,
            'type' => $this->type,
            'severity' => $this->severity,
            'headline' => $this->headline,
            'location' => $this->location,
            'operator' => $this->operatorName,
            'details' => $this->details,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
