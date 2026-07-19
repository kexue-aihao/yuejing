<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class LibraryAndThemeTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_empty_library_does_not_render_placeholder_books(): void
    {
        $response = $this->get(route('novels.index'));

        $response->assertOk()
            ->assertViewIs('pages.novels.index')
            ->assertSee('书架还是空的')
            ->assertDontSee('chaoxi-zhi-shang')
            ->assertDontSee('changan-you-xue')
            ->assertDontSee('星河失物招领处');
    }

    public function test_library_page_exposes_title_result_context_and_pagination_structure(): void
    {
        $category = Category::create(['name' => 'Context genre', 'slug' => 'context-genre']);
        $perPage = (int) config('yuejing.pagination');

        for ($index = 1; $index <= $perPage + 1; $index++) {
            $novel = $this->createPublishedNovel(null, [
                'title' => "Context work {$index}",
                'slug' => "context-work-{$index}",
                'views_count' => $perPage + 1 - $index,
            ]);
            $novel->categories()->attach($category);
        }

        $response = $this->get(route('novels.index', [
            'q' => 'Context',
            'genre' => $category->name,
            'sort' => 'hot',
        ]));

        $response->assertOk()
            ->assertSee('<title>'.e(__('ui.library.title')).'</title>', false)
            ->assertSee('id="global-search" name="q" value="Context"', false)
            ->assertSee('option value="hot" selected', false)
            ->assertSee('<nav class="pagination" role="navigation"', false)
            ->assertSee('rel="next"', false)
            ->assertSee(e(route('novels.index', [
                'q' => 'Context',
                'genre' => $category->name,
                'sort' => 'hot',
                'page' => 2,
            ])), false);
    }

    public function test_library_distinguishes_an_empty_catalog_from_an_empty_filtered_result(): void
    {
        $emptyLibrary = $this->get(route('novels.index'));
        $emptyLibrary->assertOk()->assertSee(__('ui.library.empty_heading'));

        $this->createPublishedNovel(null, [
            'title' => 'Available work',
            'slug' => 'available-work',
        ]);

        $filteredEmpty = $this->get(route('novels.index', ['q' => 'missing-result']));

        $filteredEmpty->assertOk()
            ->assertSee('id="global-search" name="q" value="missing-result"', false)
            ->assertSee('class="empty-state is-filtered"', false)
            ->assertSee(__('ui.admin.adjust_filters'));
    }

    public function test_library_category_parameter_is_stable_when_the_page_is_localized(): void
    {
        $category = Category::create(['name' => 'Stable category', 'slug' => 'stable-category']);
        $novel = $this->createPublishedNovel(null, [
            'title' => 'Localized category work',
            'slug' => 'localized-category-work',
        ]);
        $novel->categories()->attach($category);

        $response = $this->withSession([
            'locale' => 'en',
            'locale_source' => 'manual',
        ])->get(route('novels.index', ['genre' => $category->name]));

        $response->assertOk()
            ->assertSee($novel->title)
            ->assertSee(__('ui.library.title'));
    }

    public function test_theme_toggle_exposes_eye_care_mode(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme-action="light"', false)
            ->assertSee('data-theme-action="dark"', false)
            ->assertSee('data-theme-action="eye-care"', false)
            ->assertSee('护眼');
    }
}
