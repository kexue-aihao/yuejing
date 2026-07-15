<?php

namespace Tests\Feature;

use App\Models\ReadingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_homepage_returns_the_editorial_html(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('welcome')
            ->assertSee('在阅境')
            ->assertSee('与故事相遇。')
            ->assertSee('潮汐之上')
            ->assertSee(route('novels.index'));
    }

    public function test_library_returns_published_novels_as_html(): void
    {
        $novel = $this->createPublishedNovel();
        $this->createCategoryFor($novel);
        NovelContentTestData::createDraftNovel();

        $response = $this->get(route('novels.index'));

        $response->assertOk()
            ->assertViewIs('pages.novels.index')
            ->assertSee($novel->title)
            ->assertSee($novel->slug)
            ->assertDontSee('未发布作品');
    }

    public function test_published_novel_detail_lists_only_published_chapters_as_html(): void
    {
        $novel = $this->createPublishedNovel();

        $response = $this->get(route('novels.show', $novel));

        $response->assertOk()
            ->assertViewIs('pages.novels.show')
            ->assertSee($novel->title)
            ->assertSee('潮声从远处来')
            ->assertSee('灯塔下的信')
            ->assertDontSee('尚未抵达的夏天');
        $this->assertDatabaseHas('novels', ['id' => $novel->id, 'views_count' => 1]);
    }

    public function test_published_chapter_returns_html_and_records_authenticated_progress(): void
    {
        $novel = $this->createPublishedNovel();
        $reader = \App\Models\User::factory()->create();
        $chapter = $novel->chapters()->where('chapter_number', 1)->firstOrFail();

        $response = $this->actingAs($reader)->get(route('novels.read', [
            'novel' => $novel,
            'chapter' => $chapter,
        ]));

        $response->assertOk()
            ->assertViewIs('pages.novels.read')
            ->assertSee($novel->title)
            ->assertSee($chapter->title)
            ->assertSee('海风从旧码头吹来。');
        $this->assertDatabaseHas('reading_records', [
            'user_id' => $reader->id,
            'novel_id' => $novel->id,
            'chapter_id' => $chapter->id,
            'progress' => 100,
        ]);
    }

    public function test_draft_novel_and_chapter_are_not_public(): void
    {
        $draft = \App\Models\Novel::create([
            'author_id' => \App\Models\User::factory()->create()->id,
            'title' => '未发布作品',
            'slug' => 'unpublished-novel',
            'status' => 'draft',
        ]);
        $chapter = $draft->chapters()->create([
            'chapter_number' => 1,
            'title' => '未发布章节',
            'content' => '不可见内容',
            'status' => 'draft',
        ]);

        $this->get(route('novels.show', $draft))->assertNotFound();
        $this->get(route('novels.read', [$draft, $chapter]))->assertNotFound();
    }
}

final class NovelContentTestData
{
    public static function createDraftNovel(): void
    {
        \App\Models\Novel::create([
            'author_id' => \App\Models\User::factory()->create()->id,
            'title' => '未发布作品',
            'slug' => 'draft-library-novel',
            'status' => 'draft',
        ]);
    }
}
