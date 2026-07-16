<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Novel;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class AdminAndAccountTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_admin_pages_and_api_require_admin_role(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertViewIs('pages.admin.dashboard');
        $this->actingAs($admin)->getJson('/api/admin')->assertOk()->assertJsonStructure(['users', 'novels', 'chapters', 'pending_submissions']);
    }

    public function test_approved_submission_creates_published_novel_and_first_chapter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $author = User::factory()->create(['role' => 'user']);
        $category = Category::create(['name' => '都市', 'slug' => 'dushi-admin-test']);
        $submission = Submission::create([
            'user_id' => $author->id,
            'category_id' => $category->id,
            'title' => '审核后上架的故事',
            'synopsis' => '简介',
            'manuscript' => "第一段正文。\n第二段正文。",
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->putJsonWithCsrf('/api/admin/submissions/'.$submission->id.'/review', [
                'status' => 'approved',
                'review_note' => '内容完整。',
            ])
            ->assertOk();

        $novel = Novel::where('title', '审核后上架的故事')->firstOrFail();
        $this->assertSame('published', $novel->status);
        $this->assertNotNull($novel->published_at);
        $this->assertSame($novel->id, $submission->fresh()->novel_id);
        $this->assertDatabaseHas('chapters', ['novel_id' => $novel->id, 'chapter_number' => 1, 'status' => 'published']);
    }

    public function test_account_pages_render_and_two_factor_entry_is_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.settings'))->assertOk()->assertSee('二步验证');
        $this->actingAs($user)->get(route('account.favorites'))->assertOk()->assertViewIs('pages.account.favorites');
        $this->actingAs($user)->get(route('account.reading-records'))->assertOk()->assertViewIs('pages.account.reading-records');
    }
}
