<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class AuthorWorkManagementTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_author_can_open_own_work_management_and_regular_user_cannot(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $novel = $this->createPublishedNovel($author);
        $reader = User::factory()->create(['role' => 'user']);

        $this->actingAs($author)
            ->get(route('author.novels.index'))
            ->assertOk()
            ->assertSee($novel->title);

        $this->actingAs($author)
            ->get(route('author.novels.edit', ['novel' => $novel]))
            ->assertOk()
            ->assertSee($novel->title);

        $this->actingAs($reader)
            ->get(route('author.novels.index'))
            ->assertForbidden();

        $this->actingAs($reader)
            ->get(route('author.novels.edit', ['novel' => $novel]))
            ->assertForbidden();
    }

    public function test_author_cannot_update_another_authors_work(): void
    {
        $owner = User::factory()->create(['role' => 'author']);
        $otherAuthor = User::factory()->create(['role' => 'author']);
        $novel = $this->createPublishedNovel($owner);
        $originalTitle = $novel->title;

        $this->actingAs($otherAuthor)
            ->putJsonWithCsrf(route('author.novels.update', ['novel' => $novel]), [
                'title' => '越权修改的作品',
                'category_ids' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('novels', [
            'id' => $novel->id,
            'title' => $originalTitle,
            'author_id' => $owner->id,
        ]);
    }

    public function test_author_update_replaces_cover_removes_old_file_and_syncs_categories(): void
    {
        Storage::fake('public');
        $author = User::factory()->create(['role' => 'author']);
        $novel = $this->createPublishedNovel($author);
        $oldCoverPath = 'covers/old-cover.jpg';
        Storage::disk('public')->put($oldCoverPath, 'old cover');
        $novel->update(['cover_url' => Storage::disk('public')->url($oldCoverPath)]);

        $firstCategory = Category::create([
            'name' => '悬疑',
            'slug' => 'xuan-yi-management-test',
        ]);
        $secondCategory = Category::create([
            'name' => '科幻',
            'slug' => 'ke-huan-management-test',
        ]);
        $novel->categories()->attach($firstCategory);

        $response = $this->withSession(['_token' => $token = bin2hex(random_bytes(16))])
            ->actingAs($author)
            ->post(route('author.novels.update', ['novel' => $novel]), [
                '_token' => $token,
                '_method' => 'PUT',
                'title' => '更新后的作品',
                'category_ids' => [$secondCategory->id],
                'cover' => UploadedFile::fake()->image('new-cover.png', 600, 800),
            ]);

        $response->assertRedirect();

        $updatedNovel = $novel->fresh();
        $this->assertSame('更新后的作品', $updatedNovel->title);
        $this->assertNotSame(Storage::disk('public')->url($oldCoverPath), $updatedNovel->cover_url);
        $this->assertTrue(Storage::disk('public')->exists('covers/'.basename((string) parse_url($updatedNovel->cover_url, PHP_URL_PATH))));
        Storage::disk('public')->assertMissing($oldCoverPath);
        $this->assertEquals([$secondCategory->id], $updatedNovel->categories()->pluck('categories.id')->all());
    }

    public function test_author_can_manage_own_chapters_and_cannot_manage_another_authors_chapters(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $otherAuthor = User::factory()->create(['role' => 'author']);
        $ownNovel = $this->createPublishedNovel($author);
        $otherNovel = $this->createPublishedNovel($otherAuthor, [
            'title' => '其他作者的作品',
            'slug' => 'other-author-work-management-test',
        ]);
        $otherChapter = $otherNovel->chapters()->firstOrFail();

        $this->actingAs($author)
            ->get(route('author.chapters.index', ['novel' => $ownNovel]))
            ->assertOk()
            ->assertSee('潮声从远处来');

        $createResponse = $this->actingAs($author)
            ->postJsonWithCsrf(route('author.chapters.store', ['novel' => $ownNovel]), [
                'chapter_number' => 4,
                'title' => '第四章',
                'content' => '新的章节正文。',
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonPath('title', '第四章');
        $chapterId = (int) $createResponse->json('id');
        $chapter = Chapter::findOrFail($chapterId);

        $this->actingAs($author)
            ->putJsonWithCsrf(route('author.chapters.update', ['novel' => $ownNovel, 'chapter' => $chapter]), [
                'title' => '第四章（修订）',
                'content' => '修订后的章节正文。',
                'status' => 'published',
            ])
            ->assertOk()
            ->assertJsonPath('title', '第四章（修订）');

        $this->actingAs($author)
            ->deleteJsonWithCsrf(route('author.chapters.destroy', ['novel' => $ownNovel, 'chapter' => $chapter]))
            ->assertOk();
        $this->assertDatabaseMissing('chapters', ['id' => $chapter->id]);

        $this->actingAs($author)
            ->get(route('author.chapters.index', ['novel' => $otherNovel]))
            ->assertForbidden();

        $this->actingAs($author)
            ->postJsonWithCsrf(route('author.chapters.store', ['novel' => $otherNovel]), [
                'chapter_number' => 9,
                'title' => '越权章节',
                'content' => '不应创建。',
            ])
            ->assertForbidden();

        $this->actingAs($author)
            ->putJsonWithCsrf(route('author.chapters.update', ['novel' => $otherNovel, 'chapter' => $otherChapter]), [
                'title' => '越权修改',
            ])
            ->assertForbidden();

        $this->actingAs($author)
            ->deleteJsonWithCsrf(route('author.chapters.destroy', ['novel' => $otherNovel, 'chapter' => $otherChapter]))
            ->assertForbidden();

        $this->assertDatabaseHas('chapters', ['id' => $otherChapter->id, 'title' => $otherChapter->title]);
    }

    public function test_approved_submission_history_exposes_work_management_entry(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $novel = $this->createPublishedNovel($author, [
            'title' => '已审核作品',
            'slug' => 'approved-submission-history-test',
        ]);
        Submission::create([
            'user_id' => $author->id,
            'novel_id' => $novel->id,
            'title' => $novel->title,
            'synopsis' => $novel->synopsis,
            'manuscript' => '首章正文',
            'manuscript_format' => 'markdown',
            'status' => 'approved',
        ]);

        $this->actingAs($author)
            ->get(route('author.submissions'))
            ->assertOk()
            ->assertSee($novel->title)
            ->assertSee('href="'.route('author.novels.edit', ['novel' => $novel]).'"', false);
    }

    public function test_work_management_pages_render_in_zh_cn_and_english_without_untranslated_keys(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $novel = $this->createPublishedNovel($author, [
            'title' => '多语种作品管理',
            'slug' => 'multilingual-work-management-test',
        ]);

        foreach ([
            'zh_CN' => 'zh-CN',
            'en' => 'en',
        ] as $locale => $htmlLocale) {
            $index = $this->withSession(['locale' => $locale])
                ->actingAs($author)
                ->get(route('author.novels.index'))
                ->assertOk();
            $indexHtml = $index->getContent();
            $this->assertStringContainsString('<html lang="'.$htmlLocale.'"', $indexHtml);
            $this->assertStringNotContainsString('ui.', $indexHtml);

            $edit = $this->withSession(['locale' => $locale])
                ->actingAs($author)
                ->get(route('author.novels.edit', ['novel' => $novel]));
            $edit->assertOk();
            $editHtml = $edit->getContent();
            $this->assertStringContainsString('<html lang="'.$htmlLocale.'"', $editHtml);
            $this->assertStringNotContainsString('ui.', $editHtml);
        }
    }
}
