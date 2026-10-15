<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Safety\RaiseResidentPanicAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Safety\ResidentPanicRequest;
use Illuminate\Http\JsonResponse;

class ResidentEmergencyController extends Controller
{
    /**
     * Dispatch an emergency resident SOS panic alert in real-time.
     */
    public function panic(ResidentPanicRequest $request, RaiseResidentPanicAction $raise): JsonResponse
    {
        $raise->execute($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            // Keep the resident-side response intentionally generic so it
            // doesn't expose distress state on a screen someone else may see.
            'message' => 'Your request has been received.',
        ]);
    }
}
