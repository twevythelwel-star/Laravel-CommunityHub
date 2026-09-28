<?php

namespace App\Events;

use App\Models\AccessLogEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Every line written to the access log (resident scans, visitor check-ins,
 * refusals), for the gate kiosks and handhelds. Fired from the model, so no
 * code path that writes the log can skip it.
 *
 * After commit: a scan rolled back with its transaction was never recorded.
 * ShouldRescue: a Reverb outage never fails the scan that fired it.
 */
class AccessLogRecorded implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var array{id: int, userName: string|null, userRole: string|null, result: string, gate: string|null, occurredAt: string|null, denyReason: string|null} */
    public array $payload;

    public function __construct(AccessLogEntry $entry)
    {
        $this->payload = [
            'id' => $entry->id,
            'userName' => $entry->user_name,
            'userRole' => $entry->user_role,
            'result' => $entry->result,
            'gate' => $entry->gate,
            'occurredAt' => $entry->occurred_at?->toIso8601String(),
            'denyReason' => $entry->deny_reason,
        ];
    }

    public function broadcastOn(): array
    {
        // Gate staff only (routes/channels.php): residents' movements are not
        // for other residents.
        return [new PrivateChannel('gatehouse-stream')];
    }

    /**
     * The kiosk row, not the entry: the validation report and user ids stay
     * on the server.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastAs(): string
    {
        return 'access.recorded';
    }
}
