<?php

namespace App\Http\Controllers;

use App\Models\PrivateConversation;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrivateMessageController extends Controller
{
    private const MAX_BODY_LENGTH = 5000;

    public function users(Request $request)
    {
        $user = $this->authenticatedUser($request);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $term = trim((string) ($data['q'] ?? $data['search'] ?? ''));

        $users = DB::table('users')
            ->select(['id', 'name', 'role'])
            ->where('id', '<>', $user->id)
            ->when($term !== '', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['data' => $users]);
    }

    public function index(Request $request)
    {
        $user = $this->authenticatedUser($request);

        $conversations = DB::table('private_conversations')
            ->where(fn (Builder $query) => $query
                ->where('user_low_id', $user->id)
                ->orWhere('user_high_id', $user->id))
            ->get();

        if ($conversations->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $conversationIds = $conversations->pluck('id')->all();
        $messages = DB::table('private_messages')
            ->whereIn('private_conversation_id', $conversationIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('private_conversation_id');

        $otherUserIds = $conversations->map(fn (object $conversation) =>
            (int) ($conversation->user_low_id === $user->id
                ? $conversation->user_high_id
                : $conversation->user_low_id)
        )->unique()->values();

        $users = DB::table('users')
            ->select(['id', 'name', 'role'])
            ->whereIn('id', $otherUserIds)
            ->get()
            ->keyBy('id');

        $result = $conversations->map(function (object $conversation) use ($messages, $users, $user): array {
            $conversationMessages = $messages->get($conversation->id, collect());
            $otherUserId = (int) ($conversation->user_low_id === $user->id
                ? $conversation->user_high_id
                : $conversation->user_low_id);
            $lastMessage = $conversationMessages->first();

            return [
                'id' => (int) $conversation->id,
                'user_low_id' => (int) $conversation->user_low_id,
                'user_high_id' => (int) $conversation->user_high_id,
                'participant' => $users->get($otherUserId),
                'last_message' => $lastMessage ? $this->messagePayload($lastMessage) : null,
                'unread_count' => $conversationMessages
                    ->where('sender_id', '<>', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ];
        })->values()->all();

        usort($result, fn (array $left, array $right): int =>
            ($right['last_message']['id'] ?? 0) <=> ($left['last_message']['id'] ?? 0)
        );

        return response()->json(['data' => $result]);
    }

    public function show(Request $request, PrivateConversation $conversation)
    {
        $user = $this->authenticatedUser($request);
        $conversation = $this->authorizedConversation($conversation, $user->id);

        $messages = DB::table('private_messages')
            ->where('private_conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(fn (object $message): array => $this->messagePayload($message))
            ->values();

        return response()->json([
            'conversation' => [
                'id' => (int) $conversation->id,
                'user_low_id' => (int) $conversation->user_low_id,
                'user_high_id' => (int) $conversation->user_high_id,
            ],
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authenticatedUser($request);
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['nullable', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ]);

        $recipientId = (int) $data['recipient_id'];
        if ($recipientId === (int) $user->id) {
            throw ValidationException::withMessages([
                'recipient_id' => __('private.self_message'),
            ]);
        }

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('private.whitespace_body'),
            ]);
        }

        $lowId = min((int) $user->id, $recipientId);
        $highId = max((int) $user->id, $recipientId);

        [$conversation, $message] = DB::transaction(function () use ($lowId, $highId, $user, $body): array {
            $conversation = DB::table('private_conversations')
                ->where('user_low_id', $lowId)
                ->where('user_high_id', $highId)
                ->lockForUpdate()
                ->first();

            if (! $conversation) {
                $conversationId = DB::table('private_conversations')->insertGetId([
                    'user_low_id' => $lowId,
                    'user_high_id' => $highId,
                ]);
                $conversation = DB::table('private_conversations')->where('id', $conversationId)->first();
            }

            $messageId = DB::table('private_messages')->insertGetId([
                'private_conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $body,
                'read_at' => null,
            ]);
            $message = DB::table('private_messages')->where('id', $messageId)->first();

            return [$conversation, $message];
        });

        return response()->json([
            'conversation' => [
                'id' => (int) $conversation->id,
                'user_low_id' => (int) $conversation->user_low_id,
                'user_high_id' => (int) $conversation->user_high_id,
            ],
            'message' => $this->messagePayload($message),
        ], 201);
    }

    public function markRead(Request $request, PrivateConversation $conversation)
    {
        $user = $this->authenticatedUser($request);
        $conversation = $this->authorizedConversation($conversation, $user->id);

        $updated = DB::table('private_messages')
            ->where('private_conversation_id', $conversation->id)
            ->where('sender_id', '<>', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['updated_count' => $updated]);
    }

    public function stream(Request $request, PrivateConversation $conversation)
    {
        $user = $this->authenticatedUser($request);
        $conversation = $this->authorizedConversation($conversation, $user->id);
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $afterId = array_key_exists('after_id', $data)
            ? (int) $data['after_id']
            : (int) ($request->header('Last-Event-ID', 0) ?: 0);
        $deadline = microtime(true) + (int) ($data['timeout'] ?? 15);

        return response()->stream(function () use ($conversation, &$afterId, $deadline): void {
            $lastHeartbeat = microtime(true);

            while (microtime(true) < $deadline) {
                if (connection_aborted()) {
                    break;
                }

                $messages = DB::table('private_messages')
                    ->where('private_conversation_id', $conversation->id)
                    ->where('id', '>', $afterId)
                    ->orderBy('id')
                    ->get();

                foreach ($messages as $message) {
                    echo 'id: '.$message->id."\n";
                    echo "event: message\n";
                    echo 'data: '.json_encode($this->messagePayload($message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                    $afterId = (int) $message->id;
                }

                if ($messages->isEmpty() && microtime(true) - $lastHeartbeat >= 5) {
                    echo ": keep-alive\n\n";
                    $lastHeartbeat = microtime(true);
                }

                if (function_exists('ob_flush') && ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();

                if ($messages->isEmpty()) {
                    usleep(250000);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();
        abort_unless($user, 401);

        return $user;
    }

    private function authorizedConversation(mixed $conversation, int $userId): object
    {
        $conversationId = is_object($conversation) ? ($conversation->id ?? null) : $conversation;
        abort_unless(is_numeric($conversationId) && (int) $conversationId > 0, 404);

        $record = DB::table('private_conversations')->where('id', (int) $conversationId)->first();
        abort_unless($record, 404);
        abort_unless((int) $record->user_low_id === $userId || (int) $record->user_high_id === $userId, 403);

        return $record;
    }

    private function messagePayload(object $message): array
    {
        return [
            'id' => (int) $message->id,
            'private_conversation_id' => (int) $message->private_conversation_id,
            'sender_id' => (int) $message->sender_id,
            'body' => $message->body,
            'read_at' => $message->read_at,
            'created_at' => $message->created_at ?? null,
        ];
    }
}


