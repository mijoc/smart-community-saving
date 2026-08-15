<?php

namespace App\Http\Controllers;

use App\Services\AiInsightsService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function chat(Request $request, AiInsightsService $ai)
    {
        $user    = auth()->user();
        $groupId = (int) session('active_group_id');
        $memberId = $user->member_id;

        if (! $memberId || ! $groupId) {
            return response()->json(['error' => 'No active group or member.'], 403);
        }

        $message = trim($request->input('message', ''));
        if (! $message) {
            return response()->json(['error' => 'Empty message.'], 422);
        }

        $result = $ai->chat($message, $memberId, $groupId);

        return response()->json($result);
    }
}
