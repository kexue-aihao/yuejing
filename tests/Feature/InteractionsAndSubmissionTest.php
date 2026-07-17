<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\AuditLog;
use App\Models\Rating;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class InteractionsAndSubmissionTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_authenticated_reader_can_rate_and_update_a_rating(): void
    {
        $reader = User::factory()->create();
        $novel = $this->createPublishedNovel();

        $this->actingAs($reader)
            ->postJsonWithCsrf(route('novels.rate', $novel), [
                'rating' => 4,
                'review' => '很有画面感。',
            ])
            ->assertOk()
            ->assertJsonPath('rating.rating', 4);

        $this->actingAs($reader)
            ->postJsonWithCsrf(route('novels.rate', $novel), [
                'rating' => 5,
                'review' => '值得读完。',
            ])
            ->assertOk()
            ->assertJsonPath('rating.rating', 5);

        $this->assertDatabaseHas('ratings', [
            'user_id' => $reader->id,
            'novel_id' => $novel->id,
            'rating' => 5,
            'review' => '值得读完。',
        ]);
        $this->assertSame(1, Rating::where('user_id', $reader->id)->where('novel_id', $novel->id)->count());
    }

    public function test_authenticated_reader_can_add_and_remove_a_favorite(): void
    {
        $reader = User::factory()->create();
        $novel = $this->createPublishedNovel();

        $this->actingAs($reader)
            ->postJsonWithCsrf(route('novels.favorite', $novel))
            ->assertCreated()
            ->assertJsonPath('favorite.novel_id', $novel->id);
        $this->assertDatabaseHas('favorites', ['user_id' => $reader->id, 'novel_id' => $novel->id]);

        $this->actingAs($reader)
            ->deleteJsonWithCsrf(route('novels.unfavorite', $novel))
            ->assertOk()
            ->assertJson(['message' => 'Novel removed from favorites.']);
        $this->assertDatabaseMissing('favorites', ['user_id' => $reader->id, 'novel_id' => $novel->id]);
        $this->assertSame(0, Favorite::where('user_id', $reader->id)->where('novel_id', $novel->id)->count());
    }

    public function test_submission_form_returns_html_and_stores_a_submission(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('author.submissions'))
            ->assertOk()
            ->assertSee('新建投稿')
            ->assertSee(route('author.submissions.store'));

        $response = $this->withHeader('User-Agent', 'Yuejing-Test/1.0')
            ->actingAs($author)->postWithCsrf(route('author.submissions.store'), [
            'title' => '潮声之后',
            'genre' => '都市情感',
            'summary' => '一封信带来的重逢。',
            'content' => '第一章从旧书店开始。',
        ]);

        $response->assertRedirect(route('author.submissions'));
        $this->assertDatabaseHas('submissions', [
            'user_id' => $author->id,
            'title' => '潮声之后',
            'synopsis' => '一封信带来的重逢。',
            'manuscript' => '第一章从旧书店开始。',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'submission.created',
            'auditable_type' => Submission::class,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Yuejing-Test/1.0',
        ]);
        $audit = AuditLog::where('action', 'submission.created')->where('auditable_id', $author->submissions()->first()->id)->firstOrFail();
        $this->assertSame('潮声之后', $audit->metadata['title']);
        $this->assertSame($author->id, $audit->metadata['author_id']);
    }

    public function test_submission_requires_manuscript_and_does_not_create_partial_data(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->postJsonWithCsrf(route('submissions.store'), ['title' => '缺少正文'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manuscript');

        $this->assertDatabaseMissing('submissions', ['title' => '缺少正文']);
    }

    public function test_only_author_editor_and_admin_can_access_submission_entrypoints(): void
    {
        foreach (['author', 'editor', 'admin'] as $role) {
            $account = User::factory()->create(['role' => $role]);

            $this->actingAs($account)
                ->get(route('submissions.index'))
                ->assertOk();
            $this->actingAs($account)
                ->get(route('author.submissions'))
                ->assertOk();
        }

        $reader = User::factory()->create(['role' => 'user']);

        $this->actingAs($reader)
            ->get(route('submissions.index'))
            ->assertForbidden();
        $this->actingAs($reader)
            ->get(route('author.submissions'))
            ->assertForbidden();
        $this->actingAs($reader)
            ->postJsonWithCsrf(route('submissions.store'), [
                'title' => '越权投稿',
                'content' => '不应创建',
            ])
            ->assertForbidden();
        $this->actingAs($reader)
            ->postJsonWithCsrf(route('author.submissions.store'), [
                'title' => '越权作者投稿',
                'content' => '不应创建',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('submissions', ['user_id' => $reader->id]);
    }

    public function test_submission_is_private_to_its_author(): void
    {
        $author = User::factory()->create();
        $otherUser = User::factory()->create();
        $submission = Submission::create([
            'user_id' => $author->id,
            'title' => '私密投稿',
            'synopsis' => '简介',
            'manuscript' => '正文',
        ]);

        $this->actingAs($otherUser)
            ->getJson(route('submissions.show', $submission))
            ->assertForbidden();
        $this->actingAs($author)
            ->getJson(route('submissions.show', $submission))
            ->assertOk()
            ->assertJsonPath('title', '私密投稿');
    }
}
