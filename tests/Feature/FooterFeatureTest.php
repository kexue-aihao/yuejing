<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Novel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_footer_entries_are_real_links(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach ([
            route('novels.index'),
            route('categories.index'),
            route('about'),
            route('reading-guide'),
            route('contact'),
            route('register', ['role' => 'author']),
        ] as $url) {
            $this->assertStringContainsString('href="'.e($url).'"', $html);
        }

        $this->assertStringNotContainsString('href="#"', $html);
    }

    public function test_category_directory_only_counts_published_works(): void
    {
        $category = Category::create(['name' => 'Quiet stories', 'slug' => 'quiet-stories', 'is_active' => true]);
        $published = Novel::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'Published quiet story',
            'slug' => 'published-quiet-story',
            'status' => 'published',
        ]);
        $draft = Novel::create([
            'author_id' => User::factory()->create()->id,
            'title' => 'Draft quiet story',
            'slug' => 'draft-quiet-story',
            'status' => 'draft',
        ]);
        $published->categories()->attach($category);
        $draft->categories()->attach($category);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertViewIs('pages.categories.index')
            ->assertSee($category->name)
            ->assertSee(__('ui.categories.work_count', ['count' => 1]))
            ->assertSee(route('novels.index', ['genre' => $category->slug]));
    }

    public function test_information_pages_are_public_and_localized(): void
    {
        foreach (['about', 'reading-guide', 'contact'] as $name) {
            $this->withSession(['locale' => 'zh_CN'])
                ->get(route($name))
                ->assertOk()
                ->assertViewIs('pages.info')
                ->assertSee('<html lang="zh-CN"', false);
        }
    }

    public function test_author_can_enter_submission_workspace_from_footer(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.e(route('dashboard', ['section' => 'submissions'])).'"', false);
    }

    public function test_reader_is_given_a_public_author_contact_path(): void
    {
        $reader = User::factory()->create(['role' => 'user']);

        $this->actingAs($reader)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.e(route('contact')).'"', false);
    }
}
