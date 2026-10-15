<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Realtime\BroadcastRealtimeEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Realtime\BroadcastRealtimeEventApiRequest;
use App\Services\Realtime\RealtimeBroadcasterService;
use Illuminate\Http\JsonResponse;

class RealtimeApiController extends Controller
{
    public function __construct(
        protected RealtimeBroadcasterService $realtime
    ) {}

    /**
     * Get WebSocket & Echo connection parameters.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Real-time WebSocket configuration retrieved successfully.',
            'data' => $this->realtime->getEchoConfig(),
        ]);
    }

    /**
     * Get catalog of real-time channels, event signatures, and presence rooms.
     */
    public function channels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Real-time channel catalog retrieved successfully.',
            'data' => $this->realtime->channelCatalog(),
        ]);
    }

    /**
     * Dispatch a test or production real-time broadcast event.
     *
     * Chat is any active account's, posting as itself. The rest speak for
     * the estate — a security alert, a pass changing state, the dashboard's
     * figures — so they are the security desk's (`manageSecurity`), and a
     * notification pushed to another user is an administrator's
     * (`broadcastNotices`).
     */
    public function broadcast(BroadcastRealtimeEventApiRequest $request, BroadcastRealtimeEventAction $action): JsonResponse
    {
        $event = $action->execute($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Real-time event successfully broadcasted to WebSocket channels.',
            'event' => class_basename($event),
            'channels' => array_map(fn ($ch) => (string) $ch, $event->broadcastOn()),
            'payload' => method_exists($event, 'broadcastWith') ? $event->broadcastWith() : null,
        ]);
    }
}
