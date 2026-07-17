<?php

namespace Tests\Feature;

use App\Models\ChatGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MessagingSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_conversation_endpoints_are_member_only_and_csrf_protected(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $outsider = User::factory()->create();
        $body = '<script>alert(1)</script>';

        $conversationId = $this->actingAs($alice)
            ->postJsonWithCsrf(route('api.messages.store'), [
                'recipient_id' => $bob->id,
                'body' => $body,
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', $body)
            ->json('conversation.id');

        $this->actingAs($bob)
            ->getJson(route('api.messages.show', $conversationId))
            ->assertOk()
            ->assertJsonPath('messages.0.body', $body);

        $privateStream = $this->actingAs($bob)->get(route('api.messages.stream', [
            'conversation' => $conversationId,
            'after_id' => 0,
            'timeout' => 1,
        ]));
        $privateStream->assertOk();
        $this->assertStringContainsString($body, $privateStream->streamedContent());

        $this->actingAs($outsider)
            ->getJson(route('api.messages.show', $conversationId))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->getJson(route('api.messages.index'))
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAs($outsider)
            ->postJsonWithCsrf(route('api.messages.read', $conversationId))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('api.messages.stream', [
                'conversation' => $conversationId,
                'after_id' => 0,
                'timeout' => 1,
            ]))
            ->assertForbidden();

        foreach (['api.messages.store', 'api.groups.messages.store'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} must be registered.");

            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware, "Route {$routeName} must use the web middleware.");
            $this->assertContains('auth', $middleware, "Route {$routeName} must use the auth middleware.");
        }
    }

    public function test_group_endpoints_are_member_only_and_message_ids_are_group_scoped(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $groupA = $this->createGroup($ownerA, 'Group A');
        $groupB = $this->createGroup($ownerB, 'Group B');

        $this->sendGroupMessage($ownerA, $groupA, 'only group A');
        $messageB = $this->sendGroupMessage($ownerB, $groupB, 'only group B');

        $this->actingAs($ownerA)
            ->getJson(route('api.groups.show', $groupB))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->postJsonWithCsrf(route('api.groups.messages.store', $groupB), ['body' => 'cross-group send'])
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->postJsonWithCsrf(route('api.groups.read', $groupB), ['latest' => true])
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->get(route('api.groups.stream', [
                'group' => $groupB,
                'after_id' => 0,
                'timeout' => 1,
            ]))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->postJsonWithCsrf(route('api.groups.read', $groupA), ['message_id' => $messageB])
            ->assertNotFound();

        $this->assertDatabaseMissing('chat_group_message_reads', [
            'chat_group_message_id' => $messageB,
            'user_id' => $ownerA->id,
        ]);

        $stream = $this->actingAs($ownerA)->get(route('api.groups.stream', [
            'group' => $groupA,
            'after_id' => 0,
            'timeout' => 1,
        ]));
        $stream->assertOk();
        $streamedContent = $stream->streamedContent();
        $this->assertStringContainsString('only group A', $streamedContent);
        $this->assertStringNotContainsString('only group B', $streamedContent);

        $this->actingAs($ownerA)
            ->deleteJsonWithCsrf(route('api.groups.members.remove', [$groupA, $ownerB]))
            ->assertNotFound();

        $this->assertDatabaseHas('chat_group_members', [
            'chat_group_id' => $groupB->id,
            'user_id' => $ownerB->id,
        ]);
    }

    public function test_group_messages_reject_whitespace_and_return_body_in_json_and_sse(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner, 'Body output group');
        $body = '<b>message body</b>';

        $this->actingAs($owner)
            ->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $messageId = $this->actingAs($owner)
            ->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => "  {$body}  "])
            ->assertCreated()
            ->assertJsonPath('data.body', $body)
            ->json('data.id');

        $this->actingAs($owner)
            ->getJson(route('api.groups.show', $group))
            ->assertOk()
            ->assertJsonPath('messages.0.id', $messageId)
            ->assertJsonPath('messages.0.body', $body);

        $stream = $this->actingAs($owner)->get(route('api.groups.stream', [
            'group' => $group,
            'after_id' => 0,
            'timeout' => 1,
        ]));
        $stream->assertOk();
        $streamedContent = $stream->streamedContent();
        $this->assertStringContainsString('event: message', $streamedContent);
        $this->assertStringContainsString($body, $streamedContent);
    }

    private function createGroup(User $owner, string $name): ChatGroup
    {
        $response = $this->actingAs($owner)
            ->postJsonWithCsrf(route('api.groups.store'), ['name' => $name]);

        $response->assertCreated();

        return ChatGroup::findOrFail($response->json('group.id'));
    }

    private function sendGroupMessage(User $sender, ChatGroup $group, string $body): int
    {
        return $this->actingAs($sender)
            ->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => $body])
            ->assertCreated()
            ->json('data.id');
    }
}
