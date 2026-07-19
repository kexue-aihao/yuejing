<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Category;
use App\Models\Novel;
use App\Models\Rating;
use App\Models\ReadingRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_homepage_returns_the_editorial_html(): void
    {
        $novel = $this->createPublishedNovel();
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('welcome')
            ->assertSee('在阅境')
            ->assertSee('与故事相遇。')
            ->assertSee($novel->title)
            ->assertDontSee('8,642')
            ->assertSee(__('ui.home.updated_to', ['count' => 2]))
            ->assertSee(route('novels.index'));
    }

    public function test_homepage_uses_the_latest_published_chapter_number(): void
    {
        $novel = $this->createPublishedNovel();
        $novel->chapters()->create([
            'chapter_number' => 4,
            'title' => 'Published chapter four',
            'content' => 'Published content',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('ui.home.updated_to', ['count' => 4]));
    }

    public function test_library_returns_published_novels_as_html(): void
    {
        $novel = $this->createPublishedNovel();
        $this->createCategoryFor($novel);
        NovelContentTestData::createDraftNovel();

        $response = $this->get(route('novels.index'));

        $response->assertOk()
            ->assertViewIs('pages.novels.index')
            ->assertSee('<section class="site-shell page-content library-page">', false)
            ->assertSee($novel->title)
            ->assertSee($novel->slug)
            ->assertDontSee('未发布作品');
    }

    public function test_library_json_applies_combined_search_category_and_sort_conditions(): void
    {
        $category = Category::create(['name' => 'Stable genre', 'slug' => 'stable-genre']);
        $matched = $this->createPublishedNovel(null, [
            'title' => 'Signal in the Stable genre',
            'slug' => 'signal-stable-genre',
            'views_count' => 80,
        ]);
        $matched->categories()->attach($category);

        $wrongCategory = $this->createPublishedNovel(null, [
            'title' => 'Signal in another genre',
            'slug' => 'signal-another-genre',
            'views_count' => 100,
        ]);
        $this->createCategoryFor($wrongCategory, 'Other genre');

        $wrongQuery = $this->createPublishedNovel(null, [
            'title' => 'Different title in the Stable genre',
            'slug' => 'different-stable-genre',
            'views_count' => 120,
        ]);
        $wrongQuery->categories()->attach($category);

        $response = $this->getJson(route('novels.index', [
            'q' => 'Signal',
            'genre' => $category->name,
            'sort' => 'hot',
        ]));

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.slug', $matched->slug);
    }

    public function test_library_json_page_two_is_reachable_for_category_and_sort(): void
    {
        $category = Category::create(['name' => 'Page genre', 'slug' => 'page-genre']);
        $perPage = (int) config('yuejing.pagination');

        for ($index = 1; $index <= $perPage + 1; $index++) {
            $novel = $this->createPublishedNovel(null, [
                'title' => "Page work {$index}",
                'slug' => "page-work-{$index}",
                'views_count' => $perPage + 1 - $index,
            ]);
            $novel->categories()->attach($category);
        }

        $response = $this->getJson(route('novels.index', [
            'genre' => $category->name,
            'sort' => 'hot',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', $perPage + 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'page-work-'.($perPage + 1));
    }

    public function test_home_reader_stat_counts_only_readers_of_published_works(): void
    {
        $published = $this->createPublishedNovel();
        $publishedReader = User::factory()->create();
        $draft = Novel::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'Draft work',
            'slug' => 'draft-home-work',
            'status' => 'draft',
        ]);
        $draftChapter = $draft->chapters()->create([
            'chapter_number' => 1,
            'title' => 'Draft chapter',
            'content' => 'Draft content',
            'status' => 'draft',
        ]);

        ReadingRecord::create(['user_id' => $publishedReader->id, 'novel_id' => $published->id, 'chapter_id' => $published->chapters()->first()->id, 'progress' => 100, 'last_read_at' => now()]);
        ReadingRecord::create(['user_id' => User::factory()->create()->id, 'novel_id' => $draft->id, 'chapter_id' => $draftChapter->id, 'progress' => 100, 'last_read_at' => now()]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('homeStats')['readers_count']);
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

        $jsonResponse = $this->getJson(route('novels.show', $novel));
        $jsonResponse
            ->assertOk()
            ->assertJsonPath('chapters', 2);
        $this->assertStringNotContainsString('尚未抵达的夏天', $jsonResponse->getContent());
    }

    public function test_novel_detail_statistics_follow_database_changes(): void
    {
        $novel = $this->createPublishedNovel();
        $novel->update(['views_count' => 7]);

        $this->get(route('novels.show', $novel));
        $this->getJson(route('novels.show', $novel))
            ->assertOk()
            ->assertJsonPath('views_count', 8)
            ->assertJsonPath('published_chapters_count', 2)
            ->assertJsonPath('favorites_count', 0)
            ->assertJsonPath('reviews_count', 0);

        Favorite::create(['user_id' => User::factory()->create()->id, 'novel_id' => $novel->id]);
        Rating::create([
            'user_id' => User::factory()->create()->id,
            'novel_id' => $novel->id,
            'rating' => 8.5,
            'review' => 'A real review',
        ]);

        $this->getJson(route('novels.show', $novel))
            ->assertOk()
            ->assertJsonPath('views_count', 8)
            ->assertJsonPath('favorites_count', 1)
            ->assertJsonPath('reviews_count', 1);
    }

    public function test_novel_detail_has_an_empty_review_state_without_active_reviews(): void
    {
        $novel = $this->createPublishedNovel();

        $response = $this->get(route('novels.show', $novel));

        $response->assertOk()
            ->assertSee(__('reviews.no_rating'))
            ->assertSee('data-vue-reviews', false)
            ->assertSee('data-initial-statistics=', false);
        $this->assertSame(0, $response->viewData('statistics')['reviews_count']);
        $this->assertCount(0, $response->viewData('ratings'));
    }

    public function test_novel_detail_counts_only_published_chapters_and_active_reviews(): void
    {
        $novel = $this->createPublishedNovel();
        $chapters = $novel->chapters()->orderBy('chapter_number')->get();
        DB::table('chapters')->where('id', $chapters[0]->id)->update(['content' => 'abcd', 'published_at' => '2026-01-02 10:00:00', 'updated_at' => '2026-01-02 10:00:00']);
        DB::table('chapters')->where('id', $chapters[1]->id)->update(['content' => 'efghij', 'published_at' => '2026-01-03 10:00:00', 'updated_at' => '2026-01-03 10:00:00']);
        DB::table('chapters')->where('id', $chapters[2]->id)->update(['content' => 'draft content', 'published_at' => null, 'updated_at' => '2026-01-04 10:00:00']);

        $activeReviewer = User::factory()->create();
        $withdrawnReviewer = User::factory()->create();
        Rating::create([
            'user_id' => $activeReviewer->id,
            'novel_id' => $novel->id,
            'rating' => 7,
            'review' => 'Visible review',
        ]);
        Rating::create([
            'user_id' => $withdrawnReviewer->id,
            'novel_id' => $novel->id,
            'rating' => 6,
            'review' => 'Withdrawn review',
            'withdrawn_at' => Carbon::parse('2026-01-05 10:00:00'),
        ]);
        Novel::withoutTimestamps(fn () => $novel->update(['updated_at' => Carbon::parse('2026-01-01 10:00:00')]));

        $response = $this->getJson(route('novels.show', $novel));

        $response->assertOk()
            ->assertJsonPath('published_chapters_count', 2)
            ->assertJsonPath('word_count', 10)
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('active_ratings.0.review', 'Visible review');
        $this->assertStringStartsWith('2026-01-03T10:00:00', (string) $response->json('last_updated_at'));
    }

    public function test_public_review_api_returns_live_aggregate_and_excludes_withdrawn_reviews(): void
    {
        $novel = $this->createPublishedNovel();
        $firstReader = User::factory()->create();
        $secondReader = User::factory()->create();
        Rating::create(['user_id' => $firstReader->id, 'novel_id' => $novel->id, 'rating' => 7, 'review' => 'First review']);
        Rating::create(['user_id' => $secondReader->id, 'novel_id' => $novel->id, 'rating' => 9, 'review' => 'Second review']);

        $this->getJson(route('api.novels.reviews', $novel))
            ->assertOk()
            ->assertJsonPath('statistics.average_rating', 8)
            ->assertJsonPath('statistics.rating_count', 2)
            ->assertJsonCount(2, 'reviews');
        $this->assertDatabaseHas('novels', ['id' => $novel->id, 'views_count' => 0]);

        $this->actingAs($firstReader)
            ->deleteJsonWithCsrf(route('novels.rating.withdraw', $novel))
            ->assertOk();

        $this->getJson(route('api.novels.reviews', $novel))
            ->assertOk()
            ->assertJsonPath('statistics.average_rating', 9)
            ->assertJsonPath('statistics.rating_count', 1)
            ->assertJsonCount(1, 'reviews')
            ->assertJsonPath('reviews.0.review', 'Second review');
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
