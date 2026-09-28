<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GatePassEngine;
use App\Services\RealtimeConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Token auth for the Capacitor shell and handheld scanners. */
class AuthApiController extends Controller
{
    public function __construct(private readonly RealtimeConfig $realtime) {}

    public function login(Request $request, GatePassEngine $engine): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        $engine->issuePassFor($user);
        $user->recordActivity("Signed in from {$validated['device_name']}");

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
            'user' => $this->userPayload($user),
            'realtime' => $this->realtime->forDevice($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
            // Null when Reverb is off: poll instead. Channels and events to
            // join otherwise, authorised at POST /api/broadcasting/auth.
            'realtime' => $this->realtime->forDevice($request->user()),
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'displayName' => $user->display_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->value,
            'lot' => $user->lot,
            'street' => $user->street,
            'title' => $user->title,
            'avatarUrl' => $user->avatar_url,
            'property' => $user->propertyLabel(),
        ];
    }
}
