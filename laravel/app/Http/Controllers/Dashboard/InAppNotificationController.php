<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\MarkInAppNotificationReadRequest;
use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class InAppNotificationController extends Controller
{
    public function markRead(MarkInAppNotificationReadRequest $request, InAppNotification $notification): RedirectResponse|JsonResponse
    {
        $notification->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(MarkInAppNotificationReadRequest $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        InAppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
