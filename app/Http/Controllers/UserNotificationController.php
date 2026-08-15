<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function markRead(UserNotification $notification): \Illuminate\Http\JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['ok' => false], 403);
        }
        $notification->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(): \Illuminate\Http\RedirectResponse
    {
        UserNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
