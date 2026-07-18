<?php

namespace Tests\Feature;

use App\Http\Controllers\PrivateMessageController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrivateMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('private_conversations')) {
            Schema::create('private_conversations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_low_id');
                $table->unsignedBigInteger('user_high_id');
                $table->timestamps();
                $table->unique(['user_low_id', 'user_high_id']);
            });
        }

        if (! Schema::hasTable('private_messages')) {
            Schema::create('private_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('private_conversation_id');
                $table->unsignedBigInteger('sender_id');
                $table->text('body');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }


    }

    public function test_private_message_creates_and_reuses_normalized_conversation(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'role' => 'reader']);
        $bob = User::factory()->create(['name' => 'Bob', 'role' => 'author']);

        $first = $this->actingAs($alice)->postJsonWithCsrf(route('api.messages.store'), [
            'recipient_id' => $bob->id,
            'body' => '  Hello Bob  ',
        ]);

        $first->assertCreated()
            ->assertJsonPath('conversation.user_low_id', min($alice->id, $bob->id))
            ->assertJsonPath('conversation.user_high_id', max($alice->id, $bob->id))
            ->assertJsonPath('message.sender_id', $alice->id)
            ->assertJsonPath('message.body', 'Hello Bob')
            ->assertJsonPath('message.read_at', null);

        $conversationId = $first->json('conversation.id');

        $second = $this->actingAs($bob)->postJsonWithCsrf(route('api.messages.store'), [
            'recipient_id' => $alice->id,
            'body' => 'Hi Alice',
        ]);

        $second->assertCreated()
            ->assertJsonPath('conversation.id', $conversationId)
            ->assertJsonPath('message.sender_id', $bob->id);

        $this->assertDatabaseCount('private_conversations', 1);
        $this->assertDatabaseCount('private_messages', 2);

        $this->actingAs($alice)
            ->getJson(route('api.messages.index'))
            ->assertOk()
            ->assertJsonPath('data.0.participant.id', $bob->id)
            ->assertJsonPath('data.0.last_message.body', 'Hi Alice')
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_non_member_cannot_view_or_mark_a_conversation_read(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $outsider = User::factory()->create();
        $conversationId = $this->sendMessage($alice, $bob, 'Private content');

        $this->actingAs($outsider)
            ->getJson(route('api.messages.show', $conversationId))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJsonWithCsrf(route('api.messages.read', $conversationId))
            ->assertForbidden();
    }

    public function test_mark_read_only_marks_messages_sent_by_the_other_member(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversationId = $this->sendMessage($alice, $bob, 'Unread for Bob');

        $ownMessageId = DB::table('private_messages')->insertGetId([
            'private_conversation_id' => $conversationId,
            'sender_id' => $bob->id,
            'body' => 'Unread for Alice',
            'read_at' => null,
        ]);

        $this->actingAs($bob)
            ->postJsonWithCsrf(route('api.messages.read', $conversationId))
            ->assertOk()
            ->assertJsonPath('updated_count', 1);

        $this->assertNotNull(DB::table('private_messages')->where('id', DB::table('private_messages')->min('id'))->value('read_at'));
        $this->assertNull(DB::table('private_messages')->where('id', $ownMessageId)->value('read_at'));

        $show = $this->actingAs($bob)
            ->getJson(route('api.messages.show', $conversationId));
        $show->assertOk();
        $this->assertNotNull($show->json('messages.0.read_at'));
    }

    public function test_user_search_excludes_the_authenticated_user_and_returns_public_fields(): void
    {
        $alice = User::factory()->create(['name' => 'Alice', 'role' => 'reader', 'email' => 'alice@example.test']);
        $bob = User::factory()->create(['name' => 'Bobby', 'role' => 'author', 'email' => 'bob@example.test']);
        User::factory()->create(['name' => 'Carol', 'role' => 'editor']);

        $this->actingAs($alice)
            ->getJson(route('api.messages.users', ['q' => 'Bob']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bob->id)
            ->assertJsonPath('data.0.name', 'Bobby')
            ->assertJsonPath('data.0.role', 'author')
            ->assertJsonMissingPath('data.0.email');
    }

    public function test_store_validates_recipient_body_and_self_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice)
            ->postJsonWithCsrf(route('api.messages.store'), [
                'recipient_id' => $alice->id,
                'body' => 'Not allowed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id')
            ->assertJsonPath('errors.recipient_id.0', __('private.self_message'))
            ->assertJsonMissing(['errors' => ['recipient_id' => ['You cannot send a private message to yourself.']]]);

        $this->actingAs($alice)
            ->postJsonWithCsrf(route('api.messages.store'), [
                'recipient_id' => $bob->id,
                'body' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', __('private.whitespace_body'))
            ->assertJsonMissing(['errors' => ['body' => ['The body field must contain at least one non-whitespace character.']]]);

        $this->actingAs($alice)
            ->postJsonWithCsrf(route('api.messages.store'), [
                'recipient_id' => 999999,
                'body' => 'Unknown recipient',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');

        $this->actingAs($alice)
            ->postJsonWithCsrf(route('api.messages.store'), [
                'recipient_id' => $bob->id,
                'body' => str_repeat('x', 5001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('private_conversations', 0);
        $this->assertDatabaseCount('private_messages', 0);
    }

    public function test_stream_returns_sse_headers_and_messages_after_the_cursor(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversationId = $this->sendMessage($alice, $bob, 'Stream me');

        $response = $this->actingAs($bob)->withHeaders(['Accept' => 'text/event-stream'])
            ->get(route('api.messages.stream', [
                'conversation' => $conversationId,
                'after_id' => 0,
                'timeout' => 1,
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->assertHeader('X-Accel-Buffering', 'no');

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('event: message', $streamedContent);
        $this->assertStringContainsString('Stream me', $streamedContent);
    }

    private function sendMessage(User $sender, User $recipient, string $body): int
    {
        return $this->actingAs($sender)
            ->postJsonWithCsrf(route('api.messages.store'), [
                'recipient_id' => $recipient->id,
                'body' => $body,
            ])
            ->assertCreated()
            ->json('conversation.id');
    }
}

