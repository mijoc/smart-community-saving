<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatboardController extends Controller
{
    public function index(Request $request)
    {
        $u        = auth()->user();
        $activeId = (int) session('active_group_id');

        // Super-admins browsing globally still need a pinned group to chat in,
        // because the chatboard is per-group. Send them to pick one.
        if (! $activeId) {
            return redirect()
                ->route('groups.select')
                ->with('error', 'Pick a group to open its chatboard.');
        }

        if (! $u->canAccessGroup($activeId) && ! $u->isSuperAdmin()) {
            abort(403);
        }

        $messages = ChatMessage::with('user:id,name,avatar_path')
            ->where('group_id', $activeId)
            ->orderBy('created_at')
            ->limit(500)
            ->get();

        // Mark the chatboard as seen for this user (clears the unread badge).
        $u->forceFill(['chat_last_seen_at' => now()])->save();

        return view('chatboard.index', [
            'messages'   => $messages,
            'groupId'    => $activeId,
            'lastId'     => $messages->last()?->id ?? 0,
        ]);
    }

    public function store(Request $request)
    {
        $u        = auth()->user();
        $activeId = (int) session('active_group_id');

        if (! $activeId) abort(400, 'No active group.');
        if (! $u->canAccessGroup($activeId) && ! $u->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $msg = ChatMessage::create([
            'group_id' => $activeId,
            'user_id'  => $u->id,
            'body'     => trim($data['body']),
        ]);

        $u->forceFill(['chat_last_seen_at' => now()])->save();

        if ($request->wantsJson() || $request->ajax()) {
            $msg->load('user:id,name,avatar_path');
            return response()->json(['ok' => true, 'message' => $this->serialize($msg)]);
        }

        return redirect()->route('chatboard.index');
    }

    public function poll(Request $request): JsonResponse
    {
        $u        = auth()->user();
        $activeId = (int) session('active_group_id');

        if (! $activeId) return response()->json(['messages' => []]);
        if (! $u->canAccessGroup($activeId) && ! $u->isSuperAdmin()) {
            abort(403);
        }

        $afterId = (int) $request->query('after_id', 0);

        $messages = ChatMessage::with('user:id,name,avatar_path')
            ->where('group_id', $activeId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(100)
            ->get();

        $u->forceFill(['chat_last_seen_at' => now()])->save();

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->serialize($m))->all(),
        ]);
    }

    public function destroy(ChatMessage $message)
    {
        $u = auth()->user();

        // Sender can delete their own; super-admin and group_admin can delete any.
        $canDelete = $u->id === $message->user_id
            || $u->isSuperAdmin()
            || $u->hasRole('group_admin');

        if (! $canDelete) abort(403);
        if (! $u->canAccessGroup($message->group_id) && ! $u->isSuperAdmin()) {
            abort(403);
        }

        $message->delete();
        return back()->with('status', 'Message deleted.');
    }

    protected function serialize(ChatMessage $m): array
    {
        return [
            'id'         => $m->id,
            'body'       => $m->body,
            'created_at' => $m->created_at->toIso8601String(),
            'human_time' => $m->created_at->diffForHumans(),
            'is_mine'    => $m->user_id === auth()->id(),
            'user'       => [
                'id'         => $m->user?->id,
                'name'       => $m->user?->name ?? 'Unknown',
                'avatar_url' => $m->user?->avatar_url,
            ],
        ];
    }
}
