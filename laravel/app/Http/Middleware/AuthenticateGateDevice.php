<?php

namespace App\Http\Middleware;

use App\Models\GateDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGateDevice
{
    /**
     * Authenticate physical gate controllers and hardware scanners.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-Gate-Device-Key')
            ?? $request->bearerToken()
            ?? $request->input('device_key');

        if (! $rawKey) {
            return response()->json([
                'error' => 'Device authentication required. Supply X-Gate-Device-Key header.',
            ], 401);
        }

        $device = GateDevice::findByPlainKey($rawKey);

        if (! $device || ! $device->isActive()) {
            return response()->json([
                'error' => 'Gate device identity invalid, revoked, or suspended.',
            ], 401);
        }

        // Record device activity timestamp
        $device->updateQuietly(['last_heartbeat_at' => now()]);

        $request->attributes->set('gate_device', $device);

        return $next($request);
    }
}
