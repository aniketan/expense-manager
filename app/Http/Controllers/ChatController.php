<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Models\ChatSession;
use App\Models\LlmLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    /**
     * Stream a chat response as Server-Sent Events.
     * 
     * POST /chat/stream
     * Request: { message: string, session_id: string }
     * Response: text/event-stream with events: text-delta, tool-call, error, stream-end
     */
    public function stream(Request $request, ChatService $chatService): StreamedResponse
    {
        // Remove PHP execution time limit so thinking models can reason without being killed mid-stream.
        set_time_limit(0);

        $validated = $request->validate([
            'message'    => 'required|string|max:2000',
            'session_id' => 'required|string|max:64',
        ]);

        return $chatService->streamedResponse(
            $validated['session_id'],
            $validated['message']
        );
    }

    /**
     * Get chat history for a session.
     * 
     * GET /chat/history?session_id={sessionId}
     * Response: { messages: [{role, content, created_at}, ...] }
     */
    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            return response()->json(['messages' => []]);
        }

        $session = ChatSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return response()->json(['messages' => []]);
        }

        $messages = $session->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => [
                'role'       => $msg->role,
                'content'    => $msg->content,
                'created_at' => $msg->created_at->toIso8601String(),
            ]);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Reset (delete) all messages in a chat session.
     * 
     * POST /chat/reset
     * Request: { session_id: string }
     * Response: { success: true, deleted_count: int }
     */
    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
        ]);

        $session = ChatSession::where('session_id', $validated['session_id'])->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found']);
        }

        $count = $session->messages()->count();
        $session->messages()->delete();

        $deletedLlmLogs = LlmLog::where('session_id', $validated['session_id'])->delete();

        return response()->json([
            'success'            => true,
            'deleted_count'      => $count,
            'deleted_llm_logs'   => $deletedLlmLogs,
            'message'            => "Cleared {$count} messages from chat history",
        ]);
    }
}
