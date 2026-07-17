<?php

namespace Tests\Feature;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatGroupMessageRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_create_a_group_as_owner(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $reader = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($author)->postJsonWithCsrf(route('api.groups.store'), [
            'name' => '娴ｆ粏鈧懍姘﹀ù浣哄參',
            'description' => '閸ュ绮弬棰佺稊娴溿倖绁?',
            'member_ids' => [$reader->id, $reader->id, $author->id],
        ]);

        $response->assertCreated()->assertJsonPath('group.creator_id', $author->id);
        $this->assertDatabaseHas('chat_groups', ['id' => $response->json('group.id'), 'name' => '娴ｆ粏鈧懍姘﹀ù浣哄參']);
        $this->assertSame(2, ChatGroupMember::where('chat_group_id', $response->json('group.id'))->count());
        $this->assertDatabaseHas('chat_group_members', [
            'chat_group_id' => $response->json('group.id'),
            'user_id' => $author->id,
            'role' => 'owner',
        ]);
    }

    public function test_regular_reader_can_create_a_group(): void
    {
        $reader = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($reader)->postJsonWithCsrf(route('api.groups.store'), [
            'name' => '鐠囨槒鈧懍姘﹀ù浣哄參',
            'member_ids' => [],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('chat_group_members', [
            'chat_group_id' => $response->json('group.id'),
            'user_id' => $reader->id,
            'role' => 'owner',
        ]);
    }

    public function test_reader_can_join_and_send_a_message(): void
    {
        [$owner, $reader, $group] = $this->groupWithReader();

        $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.members.add', $group), [
            'user_id' => $reader->id,
        ])->assertCreated();

        $response = $this->actingAs($reader)->postJsonWithCsrf(route('api.groups.messages.store', $group), [
            'body' => '婢堆冾啀婵傛枻绱濋幋鎴炴降閹躲儱鍩岄妴?',
        ]);

        $response->assertCreated()->assertJsonPath('data.sender_id', $reader->id);
        $this->assertDatabaseHas('chat_group_messages', [
            'chat_group_id' => $group->id,
            'sender_id' => $reader->id,
            'body' => '婢堆冾啀婵傛枻绱濋幋鎴炴降閹躲儱鍩岄妴?',
        ]);
    }

    public function test_non_member_cannot_view_send_or_mark_group_messages_read(): void
    {
        [$owner, , $group] = $this->groupWithReader();
        $outsider = User::factory()->create(['role' => 'user']);

        $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => '缂囥倕鍞村☉鍫熶紖'])->assertCreated();

        $this->actingAs($outsider)->getJson(route('api.groups.show', $group))->assertForbidden();
        $this->actingAs($outsider)->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => '鐡掑﹥娼?'])->assertForbidden();
        $this->actingAs($outsider)->postJsonWithCsrf(route('api.groups.read', $group), ['latest' => true])->assertForbidden();
        $this->actingAs($outsider)->getJson(route('api.groups.index'))->assertOk()->assertJsonPath('groups', []);
    }

    public function test_mark_read_upserts_read_rows_and_clears_unread_count(): void
    {
        [$owner, $reader, $group] = $this->groupWithReader();
        $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.members.add', $group), ['user_id' => $reader->id])->assertCreated();
        $message = $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => '鐠囩兘妲勭拠鏄忕箹閺夆剝绉烽幁?'])
            ->assertCreated()
            ->json('data');

        $this->actingAs($reader)->getJson(route('api.groups.index'))->assertJsonPath('groups.0.unread_count', 1);

        $this->actingAs($reader)->postJsonWithCsrf(route('api.groups.read', $group), [
            'message_id' => $message['id'],
        ])->assertOk()->assertJsonPath('marked_count', 1);

        $this->assertDatabaseHas('chat_group_message_reads', [
            'chat_group_message_id' => $message['id'],
            'user_id' => $reader->id,
        ]);
        $this->actingAs($reader)->getJson(route('api.groups.index'))->assertJsonPath('groups.0.unread_count', 0);
        $this->actingAs($reader)->postJsonWithCsrf(route('api.groups.read', $group), ['latest' => true])->assertOk();
        $this->assertSame(1, ChatGroupMessageRead::where('chat_group_message_id', $message['id'])->where('user_id', $reader->id)->count());
    }

    public function test_owner_and_admin_can_add_but_only_owner_can_remove_members(): void
    {
        [$owner, $reader, $group] = $this->groupWithReader();
        $admin = User::factory()->create(['role' => 'user']);
        $newMember = User::factory()->create(['role' => 'user']);
        $this->addMembership($group, $admin, 'admin');
        $this->addMembership($group, $reader, 'member');

        $this->actingAs($admin)->postJsonWithCsrf(route('api.groups.members.add', $group), ['user_id' => $newMember->id])->assertCreated();
        $this->actingAs($admin)->deleteJsonWithCsrf(route('api.groups.members.remove', [$group, $reader]))->assertForbidden();
        $this->actingAs($owner)->deleteJsonWithCsrf(route('api.groups.members.remove', [$group, $reader]))->assertOk();
        $this->assertDatabaseMissing('chat_group_members', ['chat_group_id' => $group->id, 'user_id' => $reader->id]);
        $this->actingAs($owner)->deleteJsonWithCsrf(route('api.groups.members.remove', [$group, $owner]))->assertStatus(422);
    }

    public function test_group_member_management_is_idempotent_for_duplicate_adds(): void
    {
        [$owner, $reader, $group] = $this->groupWithReader();

        $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.members.add', $group), ['user_id' => $reader->id])->assertCreated();
        $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.members.add', $group), ['user_id' => $reader->id])->assertOk();

        $this->assertSame(1, ChatGroupMember::where('chat_group_id', $group->id)->where('user_id', $reader->id)->count());
    }

    public function test_group_inputs_are_validated(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJsonWithCsrf(route('api.groups.store'), [
            'name' => str_repeat('x', 101),
            'member_ids' => [$user->id, 999999],
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'member_ids.1']);

        $group = $this->createGroup($user);
        $this->actingAs($user)->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => ''])->assertUnprocessable();
        $this->actingAs($user)->postJsonWithCsrf(route('api.groups.read', $group), [])->assertUnprocessable();
    }

    public function test_stream_returns_sse_for_new_messages_and_requires_membership(): void
    {
        [$owner, , $group] = $this->groupWithReader();
        $message = $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.messages.store', $group), ['body' => '鐎圭偞妞傚☉鍫熶紖'])
            ->assertCreated()
            ->json('data');

        $response = $this->actingAs($owner)->get(route('api.groups.stream', [
            'group' => $group->id,
            'after_id' => 0,
            'timeout' => 1,
        ]));
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->assertHeader('X-Accel-Buffering', 'no');

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('event: message', $streamedContent);
        $this->assertStringContainsString($message['body'], $streamedContent);
    }

    private function groupWithReader(): array
    {
        $owner = User::factory()->create(['role' => 'user']);
        $reader = User::factory()->create(['role' => 'user']);
        $group = $this->createGroup($owner);

        return [$owner, $reader, $group];
    }

    private function createGroup(User $owner): ChatGroup
    {
        $response = $this->actingAs($owner)->postJsonWithCsrf(route('api.groups.store'), ['name' => '濞村鐦紘?']);
        $response->assertCreated();

        return ChatGroup::findOrFail($response->json('group.id'));
    }

    private function addMembership(ChatGroup $group, User $user, string $role = 'member'): void
    {
        $member = ChatGroupMember::query()->firstOrNew([
            'chat_group_id' => $group->id,
            'user_id' => $user->id,
        ]);
        $member->forceFill([
            'role' => $role,
            'joined_at' => now(),
        ])->save();
    }
}
