<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowupSchedule;
use App\Services\FollowupDispatcher;
use App\Services\InboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Endpoints for the n8n WhatsApp workflow. Contract: docs/n8n and the workflow's "Web API contract" note. */
class N8nController extends Controller
{
    public function replies(Request $request, InboundMessageService $inbound): JsonResponse
    {
        $data = $request->validate([
            'message_id' => ['nullable', 'string', 'max:200'],
            'chat_id' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'text' => ['nullable', 'string', 'max:4000'],
            'has_media' => ['nullable', 'boolean'],
            'received_at' => ['nullable', 'date'],
            'session' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($inbound->handle($data));
    }

    public function due(Request $request, FollowupDispatcher $dispatcher): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        return response()->json(['items' => $dispatcher->due($limit)]);
    }

    public function result(Request $request, int $schedule, FollowupDispatcher $dispatcher): JsonResponse
    {
        // Looked up here, after the token check, so unauthenticated callers cannot probe ids.
        $schedule = FollowupSchedule::query()->findOrFail($schedule);

        $data = $request->validate([
            'status' => ['required', Rule::in(['sent', 'failed'])],
            'waha_message_id' => ['nullable', 'string', 'max:255'],
            'sent_at' => ['nullable', 'date'],
            'error' => ['nullable', 'string', 'max:1000'],
        ]);

        $schedule = $dispatcher->report($schedule, $data);

        return response()->json(['id' => $schedule->id, 'status' => $schedule->status]);
    }
}
