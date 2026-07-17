<?php

namespace App\Http\Controllers;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatGroupMessage;
use App\Models\ChatGroupMessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $memberTable = $this->table(ChatGroupMember::class);

        $memberships = ChatGroupMember::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('joined_at')
            ->get()
            ->keyBy('chat_group_id');

        if ($memberships->isEmpty()) {
            return response()->json(['groups' => []]);
        }

        $groups = ChatGroup::query()
            ->whereIn($this->keyName(ChatGroup::class), $memberships->keys())
            ->orderByDesc('created_at')
            ->get();

        $messagesTable = $this->table(ChatGroupMessage::class);
        $result = $groups->map(function (ChatGroup $group) use ($memberships, $messagesTable): array {
            $membership = $memberships->get($group->getKey());
            $lastReadAt = $membership?->getAttribute('last_read_at');

            $unreadQuery = DB::table($messagesTable)
                ->where('chat_group_id', $group->getKey())
                ->where('sender_id', '!=', $membership->user_id);

            if ($lastReadAt !== null) {
                $unreadQuery->where('created_at', '>', $lastReadAt);
            }

            return [
                'id' => $group->getKey(),
                'creator_id' => $group->creator_id,
                'name' => $group->name,
                'description' => $group->description,
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
                'last_read_at' => $membership->last_read_at,
                'unread_count' => $unreadQuery->count(),
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ];
        })->values();

        return response()->json(['groups' => $result]);
    }

    public function show(ChatGroup $group): JsonResponse
    {
        $this->requireMembership($group, [], request());

        $memberRows = ChatGroupMember::query()
            ->where('chat_group_id', $group->getKey())
            ->orderBy('joined_at')
            ->get();
        $users = User::query()
            ->whereIn('id', $memberRows->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $messages = ChatGroupMessage::query()
            ->where('chat_group_id', $group->getKey())
            ->orderBy('id')
            ->get();
        $readRows = $messages->isEmpty()
            ? collect()
            : ChatGroupMessageRead::query()
                ->whereIn('chat_group_message_id', $messages->pluck('id'))
                ->orderBy('user_id')
                ->get()
                ->groupBy('chat_group_message_id');

        $members = $memberRows->map(function (ChatGroupMember $member) use ($users): array {
            $user = $users->get($member->user_id);

            return [
                'id' => $user?->getKey() ?? $member->user_id,
                'name' => $user?->name,
                'role' => $member->role,
                'joined_at' => $member->joined_at,
                'last_read_at' => $member->last_read_at,
            ];
        })->values();

        $messagePayload = $messages->map(function (ChatGroupMessage $message) use ($readRows, $users): array {
            $reads = $readRows->get($message->getKey(), collect());
            $sender = $users->get($message->sender_id) ?? User::query()->find($message->sender_id);

            return [
                'id' => $message->getKey(),
                'chat_group_id' => $message->chat_group_id,
                'sender_id' => $message->sender_id,
                'sender' => $sender?->only(['id', 'name']),
                'body' => $message->body,
                'created_at' => $message->created_at,
                'updated_at' => $message->updated_at,
                'read_count' => $reads->count(),
                'read_by' => $reads->pluck('user_id')->values(),
                'reads' => $reads->map(fn (ChatGroupMessageRead $read): array => [
                    'user_id' => $read->user_id,
                    'read_at' => $read->read_at,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'group' => [
                'id' => $group->getKey(),
                'creator_id' => $group->creator_id,
                'name' => $group->name,
                'description' => $group->description,
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ],
            'members' => $members,
            'messages' => $messagePayload,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $data = $this->validated($request, [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'member_ids' => ['sometimes', 'array', 'max:100'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $memberIds = collect($data['member_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->push((int) $user->getKey())
            ->unique()
            ->values();

        $group = DB::transaction(function () use ($data, $memberIds, $user): ChatGroup {
            $group = new ChatGroup();
            $group->forceFill([
                'creator_id' => $user->getKey(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);
            $group->save();

            foreach ($memberIds as $memberId) {
                $member = new ChatGroupMember();
                $member->forceFill([
                    'chat_group_id' => $group->getKey(),
                    'user_id' => $memberId,
                    'role' => $memberId === (int) $user->getKey() ? 'owner' : 'member',
                    'joined_at' => now(),
                    'last_read_at' => null,
                ]);
                $member->save();
            }

            return $group;
        });

        return response()->json([
            'message' => 'Group created.',
            'group' => [
                'id' => $group->getKey(),
                'creator_id' => $group->creator_id,
                'name' => $group->name,
                'description' => $group->description,
            ],
            'member_ids' => $memberIds,
        ], 201);
    }

    public function addMember(Request $request, ChatGroup $group): JsonResponse
    {
        $this->requireMembership($group, ['owner', 'admin'], $request);
        $data = $this->validated($request, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $member = ChatGroupMember::query()->firstOrNew([
            'chat_group_id' => $group->getKey(),
            'user_id' => (int) $data['user_id'],
        ]);

        if ($member->exists) {
            return response()->json([
                'message' => 'User is already a member.',
                'member' => $this->memberPayload($member),
            ]);
        }

        $member->forceFill([
            'role' => 'member',
            'joined_at' => now(),
            'last_read_at' => null,
        ]);
        $member->save();

        return response()->json([
            'message' => 'Member added.',
            'member' => $this->memberPayload($member),
        ], 201);
    }

    public function removeMember(ChatGroup $group, User $user): JsonResponse
    {
        $this->requireMembership($group, ['owner'], request());

        if ((int) $user->getKey() === (int) $group->creator_id) {
            throw ValidationException::withMessages([
                'user_id' => ['The group creator cannot be removed.'],
            ]);
        }

        $member = ChatGroupMember::query()
            ->where('chat_group_id', $group->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        abort_unless($member, 404, 'User is not a member of this group.');

        $messageIds = ChatGroupMessage::query()
            ->where('chat_group_id', $group->getKey())
            ->pluck('id');

        if ($messageIds->isNotEmpty()) {
            ChatGroupMessageRead::query()
                ->where('user_id', $user->getKey())
                ->whereIn('chat_group_message_id', $messageIds)
                ->delete();
        }

        $member->delete();

        return response()->json(['message' => 'Member removed.']);
    }

    public function sendMessage(Request $request, ChatGroup $group): JsonResponse
    {
        $user = $this->requireMembership($group, [], $request);
        $data = $this->validated($request, [
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['The body field must contain at least one non-whitespace character.'],
            ]);
        }

        $message = DB::transaction(function () use ($body, $group, $user): ChatGroupMessage {
            $message = new ChatGroupMessage();
            $message->forceFill([
                'chat_group_id' => $group->getKey(),
                'sender_id' => $user->getKey(),
                'body' => $body,
            ]);
            $message->save();

            $member = ChatGroupMember::query()
                ->where('chat_group_id', $group->getKey())
                ->where('user_id', $user->getKey())
                ->first();
            $member?->forceFill(['last_read_at' => $message->created_at])?->save();

            $read = ChatGroupMessageRead::query()->firstOrNew([
                'chat_group_message_id' => $message->getKey(),
                'user_id' => $user->getKey(),
            ]);
            $read->forceFill(['read_at' => now()]);
            $read->save();

            return $message;
        });

        return response()->json([
            'message' => 'Message sent.',
            'data' => $this->messagePayload($message),
        ], 201);
    }

    public function markRead(Request $request, ChatGroup $group): JsonResponse
    {
        $user = $this->requireMembership($group, [], $request);
        $data = $this->validated($request, [
            'message_id' => ['nullable', 'integer', 'min:1'],
            'latest' => ['nullable', 'boolean'],
        ]);

        $hasMessageId = array_key_exists('message_id', $data) && $data['message_id'] !== null;
        $latest = filter_var($data['latest'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $hasMessageId && ! $latest) {
            throw ValidationException::withMessages([
                'message_id' => ['Provide message_id or set latest to true.'],
            ]);
        }

        $messageQuery = ChatGroupMessage::query()->where('chat_group_id', $group->getKey());
        $target = $hasMessageId
            ? $messageQuery->whereKey($data['message_id'])->first()
            : $messageQuery->latest('id')->first();

        abort_unless($target || ! $hasMessageId, 404, 'Message does not belong to this group.');

        if (! $target) {
            return response()->json([
                'message' => 'No messages to mark as read.',
                'message_id' => null,
                'marked_count' => 0,
            ]);
        }

        $messages = ChatGroupMessage::query()
            ->where('chat_group_id', $group->getKey())
            ->where('id', '<=', $target->getKey())
            ->get(['id']);
        $readAt = now();

        DB::transaction(function () use ($messages, $user, $readAt, $group, $target): void {
            foreach ($messages as $message) {
                $read = ChatGroupMessageRead::query()->firstOrNew([
                    'chat_group_message_id' => $message->getKey(),
                    'user_id' => $user->getKey(),
                ]);
                $read->forceFill(['read_at' => $read->read_at ?? $readAt]);
                $read->save();
            }

            $member = ChatGroupMember::query()
                ->where('chat_group_id', $group->getKey())
                ->where('user_id', $user->getKey())
                ->first();
            $lastReadAt = $member?->last_read_at;
            $targetCreatedAt = $target->created_at;
            if ($member && ($lastReadAt === null || ($targetCreatedAt !== null && Carbon::parse($targetCreatedAt)->gt(Carbon::parse($lastReadAt))))) {
                $member->forceFill(['last_read_at' => $targetCreatedAt ?? $readAt]);
                $member->save();
            }
        });

        return response()->json([
            'message' => 'Messages marked as read.',
            'message_id' => $target->getKey(),
            'marked_count' => $messages->count(),
        ]);
    }

    public function stream(Request $request, ChatGroup $group)
    {
        $user = $this->requireMembership($group, [], $request);
        $data = $request->validate([
            'after_id' => ['sometimes', 'integer', 'min:0'],
            'timeout' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'poll_ms' => ['sometimes', 'integer', 'min:50', 'max:1000'],
        ]);

        $afterId = (int) ($data['after_id'] ?? 0);
        $timeout = (int) ($data['timeout'] ?? 2);
        $pollMs = (int) ($data['poll_ms'] ?? 250);
        $groupId = $group->getKey();
        $userId = $user->getKey();

        return response()->stream(function () use ($groupId, $userId, $afterId, $timeout, $pollMs): void {
            $cursor = $afterId;
            $deadline = microtime(true) + $timeout;

            do {
                $messages = ChatGroupMessage::query()
                    ->where('chat_group_id', $groupId)
                    ->where('id', '>', $cursor)
                    ->orderBy('id')
                    ->get();

                if ($messages->isNotEmpty()) {
                    foreach ($messages as $message) {
                        echo 'id: '.$message->getKey()."\n";
                        echo "event: message\n";
                        echo 'data: '.json_encode($this->messagePayload($message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                        $cursor = $message->getKey();
                    }

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                    return;
                }

                if (connection_aborted()) {
                    return;
                }

                echo ": heartbeat\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
                usleep($pollMs * 1000);
            } while (microtime(true) < $deadline);

            echo "event: end\n";
            echo "data: {}\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function validated(Request $request, array $rules): array
    {
        $jsonPayload = $request->json()->all();
        if (is_array($jsonPayload) && $jsonPayload !== []) {
            return validator($jsonPayload, $rules)->validate();
        }

        $payload = $request->all();
        $content = trim((string) $request->getContent());
        if ($payload === [] && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded) && $decoded !== []) {
                $payload = $decoded;
            }
        }

        return validator($payload, $rules)->validate();
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Authentication required.');

        return $user;
    }

    private function requireMembership(ChatGroup $group, array $roles = [], ?Request $request = null): User
    {
        $user = $this->authenticatedUser($request ?? request());
        $member = ChatGroupMember::query()
            ->where('chat_group_id', $group->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        abort_unless($member, 403, 'You are not a member of this group.');

        if ($roles !== []) {
            abort_unless(in_array($member->role, $roles, true), 403, 'You are not allowed to manage this group.');
        }

        return $user;
    }

    private function memberPayload(ChatGroupMember $member): array
    {
        $user = User::query()->find($member->user_id);

        return [
            'id' => $user?->getKey() ?? $member->user_id,
            'name' => $user?->name,
            'role' => $member->role,
            'joined_at' => $member->joined_at,
            'last_read_at' => $member->last_read_at,
        ];
    }

    private function messagePayload(ChatGroupMessage $message): array
    {
        $sender = User::query()->find($message->sender_id);
        $reads = ChatGroupMessageRead::query()
            ->where('chat_group_message_id', $message->getKey())
            ->orderBy('user_id')
            ->get();

        return [
            'id' => $message->getKey(),
            'chat_group_id' => $message->chat_group_id,
            'sender_id' => $message->sender_id,
            'sender' => $sender?->only(['id', 'name']),
            'body' => $message->body,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
            'read_count' => $reads->count(),
            'read_by' => $reads->pluck('user_id')->values(),
        ];
    }

    private function table(string $modelClass): string
    {
        /** @var Model $model */
        $model = new $modelClass();

        return $model->getTable();
    }

    private function keyName(string $modelClass): string
    {
        /** @var Model $model */
        $model = new $modelClass();

        return $model->getKeyName();
    }
}




