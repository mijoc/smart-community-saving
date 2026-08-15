<?php

namespace App\View\Composers;

use App\Models\Activity;
use App\Models\ChatMessage;
use App\Models\ContributionPaymentRequest;
use App\Models\UserNotification;
use Illuminate\View\View;

class NotificationsComposer
{
    public function compose(View $view): void
    {
        $u = auth()->user();
        if (! $u) {
            $view->with([
                'unreadActivityCount'    => 0,
                'recentActivities'       => collect(),
                'unreadChatCount'        => 0,
                'pendingPaymentRequests' => 0,
                'personalNotifs'         => collect(),
                'unreadPersonalCount'    => 0,
            ]);
            return;
        }

        // Non-super-admins are constrained to whichever group they are
        // currently switched into. Super admins see every group.
        $activeId = session('active_group_id');
        if ($u->isSuperAdmin()) {
            $groupIds = $u->accessibleGroups()->pluck('id')->all();
        } else {
            $groupIds = $activeId ? [(int) $activeId] : [];
        }
        $since = $u->activities_last_seen_at ?: $u->created_at;

        try {
            $personalNotifs = UserNotification::where('user_id', $u->id)
                ->whereNull('read_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        } catch (\Throwable) {
            $personalNotifs = collect();
        }

        if (empty($groupIds)) {
            $view->with([
                'unreadActivityCount'    => 0,
                'recentActivities'       => collect(),
                'lastSeenAt'             => $since,
                'unreadChatCount'        => 0,
                'pendingPaymentRequests' => 0,
                'personalNotifs'         => $personalNotifs,
                'unreadPersonalCount'    => $personalNotifs->count(),
            ]);
            return;
        }

        $unread = Activity::whereIn('group_id', $groupIds)
            ->where('created_at', '>', $since)
            ->where('actor_user_id', '!=', $u->id)
            ->count();

        $recent = Activity::with(['actor:id,name,avatar_path', 'group:id,name,code'])
            ->whereIn('group_id', $groupIds)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $chatSince  = $u->chat_last_seen_at ?: $u->created_at;
        $unreadChat = ChatMessage::whereIn('group_id', $groupIds)
            ->where('created_at', '>', $chatSince)
            ->where(function ($q) use ($u) {
                $q->whereNull('user_id')->orWhere('user_id', '!=', $u->id);
            })
            ->count();

        $pendingPayReqs = 0;
        if ($u->hasAnyRole(['super_admin', 'group_admin', 'treasurer']) && $activeId) {
            $pendingPayReqs = ContributionPaymentRequest::where('group_id', $activeId)
                ->where('status', 'pending_review')
                ->count();
        }

        try {
            $personalNotifs = UserNotification::where('user_id', $u->id)
                ->whereNull('read_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();
        } catch (\Throwable) {
            $personalNotifs = collect();
        }

        $view->with([
            'unreadActivityCount'    => $unread,
            'recentActivities'       => $recent,
            'lastSeenAt'             => $since,
            'unreadChatCount'        => $unreadChat,
            'pendingPaymentRequests' => $pendingPayReqs,
            'personalNotifs'         => $personalNotifs,
            'unreadPersonalCount'    => $personalNotifs->count(),
        ]);
    }
}
