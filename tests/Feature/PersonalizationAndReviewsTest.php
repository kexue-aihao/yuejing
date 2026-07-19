<?php

namespace Tests\Feature;

use App\Models\SearchEvent;
use App\Models\User;
use App\Services\LocaleManager;
use App\Services\RatingScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class PersonalizationAndReviewsTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_timezone_detection_selects_the_dominant_local_catalog(): void
    {
        $locales = app(LocaleManager::class);

        $this->assertSame('zh_CN', $locales->localeForTimezone('Asia/Shanghai'));
        $this->assertSame('ja', $locales->localeForTimezone('Asia/Tokyo'));
        $this->assertSame('pt', $locales->localeForTimezone('America/Sao_Paulo'));
        $this->assertSame('en', $locales->localeForTimezone('UTC'));

        $this->postJsonWithCsrf(route('language.timezone'), ['timezone' => 'Asia/Tokyo'])
            ->assertOk()
            ->assertJson(['locale' => 'ja', 'changed' => true])
            ->assertCookie('yuejing_timezone', 'Asia/Tokyo');
    }

    public function test_search_categories_drive_recommendation_results_and_homepage_stream_contract(): void
    {
        $reader = User::factory()->create();
        $searched = $this->createPublishedNovel(null, ['title' => 'Mystery target', 'slug' => 'mystery-target']);
        $recommended = $this->createPublishedNovel(null, ['title' => 'Mystery follow-up', 'slug' => 'mystery-follow-up']);
        $category = $this->createCategoryFor($searched, 'Mystery');
        $recommended->categories()->attach($category);

        $this->actingAs($reader)
            ->get(route('novels.index', ['q' => 'Mystery']))
            ->assertOk();

        $this->assertDatabaseHas('search_events', [
            'user_id' => $reader->id,
            'category_id' => $category->id,
            'query' => 'Mystery',
        ]);

        $this->actingAs($reader)
            ->getJson(route('api.recommendations.index'))
            ->assertOk()
            ->assertJsonFragment(['slug' => $recommended->slug]);

        $this->actingAs($reader)
            ->get(route('api.recommendations.stream'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-recommendations-app', false)
            ->assertSee('data-api-url="'.url('/api/recommendations').'"', false);
    }

    public function test_home_recommendations_use_json_polling_instead_of_one_shot_event_source(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($script);

        $start = strpos($script, 'function initRecommendations()');
        $end = strpos($script, 'async function readManuscriptFile', $start === false ? 0 : $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $recommendationScript = substr($script, (int) $start, (int) $end - (int) $start);
        $this->assertStringContainsString('fetch(url,', $recommendationScript);
        $this->assertStringContainsString('schedulePoll(', $recommendationScript);
        $this->assertStringNotContainsString('new EventSource', $recommendationScript);
    }

    public function test_rating_gaps_are_rejected(): void
    {
        $reader = User::factory()->create();
        $novel = $this->createPublishedNovel();

        $this->actingAs($reader)
            ->postJsonWithCsrf(route('novels.rate', $novel), ['rating' => 5.5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');

        $this->assertSame(0, SearchEvent::count());
    }

    public function test_rating_levels_match_the_declared_non_contiguous_scale(): void
    {
        $scale = app(RatingScale::class);

        $this->assertSame('standard', $scale->key(5.0));
        $this->assertSame('bronze', $scale->key(6.0));
        $this->assertSame('bronze', $scale->key(7.0));
        $this->assertSame('diamond', $scale->key(8.0));
        $this->assertSame('diamond', $scale->key(9.0));
        $this->assertSame('supreme_diamond', $scale->key(9.1));
        $this->assertSame('supreme_diamond', $scale->key(9.9));
    }
}
