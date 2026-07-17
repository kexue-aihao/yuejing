<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalCenterNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_personal_center_has_no_submission_entry_or_author_center(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('个人中心导航')
            ->assertSee('阅读概览')
            ->assertSee('我的收藏')
            ->assertSee('阅读记录')
            ->assertSee('账号设置')
            ->assertDontSee('作品投稿')
            ->assertDontSee('作者中心')
            ->assertDontSee('后台');
    }

    public function test_author_personal_center_uses_submission_label_and_removes_author_center_copy(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('作品投稿')
            ->assertSee('href="'.route('author.submissions').'"', false)
            ->assertDontSee('作者中心');
    }

    public function test_editor_personal_center_has_submission_entry_without_admin_backend_entry(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('作品投稿')
            ->assertDontSee('作者中心')
            ->assertDontSee('后台');

        $this->actingAs($editor)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_personal_center_has_management_entry_and_admin_copy_is_explicit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('作品投稿')
            ->assertSee('后台')
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('管理后台')
            ->assertDontSee('作者中心');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('管理控制台')
            ->assertSee('管理后台导航')
            ->assertSee('投稿审核')
            ->assertSee('小说管理');
    }

    public function test_desktop_mobile_navigation_and_avatar_have_accessible_personal_center_semantics(): void
    {
        $user = User::factory()->create(['name' => '导航读者', 'role' => 'user']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('aria-label="主导航"', false)
            ->assertSee('aria-label="移动端主导航"', false)
            ->assertSee('aria-label="个人中心导航"', false)
            ->assertSee('aria-label="打开个人中心"', false)
            ->assertSee('title="个人中心"', false);
    }
}
