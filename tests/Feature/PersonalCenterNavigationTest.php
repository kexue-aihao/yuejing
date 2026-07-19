<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalCenterNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_navigation_reflects_guest_and_authenticated_states(): void
    {
        $guestHtml = $this->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.route('login').'"', $guestHtml);
        $this->assertStringContainsString('href="'.route('register').'"', $guestHtml);

        $user = User::factory()->create(['role' => 'user']);
        $authenticatedHtml = $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-auth-state="authenticated"', $authenticatedHtml);
        $this->assertStringContainsString('data-server-auth-state="authenticated"', $authenticatedHtml);
        $this->assertStringContainsString('>'.__('ui.nav.logged_in').'</span>', $authenticatedHtml);
        $this->assertStringContainsString('href="'.route('dashboard').'"', $authenticatedHtml);
        $this->assertStringContainsString('action="'.route('logout').'"', $authenticatedHtml);
        $this->assertStringNotContainsString('href="'.route('login').'"', $authenticatedHtml);
        $this->assertStringNotContainsString('href="'.route('register').'"', $authenticatedHtml);
    }

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

    public function test_communication_and_submission_links_are_only_in_personal_center_navigation(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $html = $this->actingAs($author)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        preg_match('/<nav class="desktop-nav"[^>]*>(.*?)<\/nav>/s', $html, $desktopMatches);
        preg_match('/<nav class="site-shell mobile-nav"[^>]*>(.*?)<\/nav>/s', $html, $mobileMatches);
        $desktopNavigation = $desktopMatches[1] ?? '';
        $mobileNavigation = $mobileMatches[1] ?? '';

        foreach (['作品投稿', '站内私信', '交流群'] as $label) {
            $this->assertStringNotContainsString($label, $desktopNavigation);
            $this->assertStringNotContainsString($label, $mobileNavigation);
        }

        preg_match('/<nav class="dashboard-nav"[^>]*>(.*?)<\/nav>/s', $html, $accountMatches);
        $accountNavigation = $accountMatches[1] ?? '';
        $this->assertStringContainsString('作品投稿', $accountNavigation);
        $this->assertStringContainsString('站内私信', $accountNavigation);
        $this->assertStringContainsString('实时交流群', $accountNavigation);
    }

    public function test_social_navigation_is_a_collapsible_module_without_replacing_other_menu_items(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<details class="dashboard-nav-group"', false)
            ->assertSee('<summary class="dashboard-nav-trigger">', false)
            ->assertSee('data-account-social-menu', false)
            ->assertSee('href="'.route('dashboard', ['section' => 'messages']).'"', false)
            ->assertSee('href="'.route('dashboard', ['section' => 'groups']).'"', false)
            ->assertDontSee('<details class="dashboard-nav-group" open', false);
    }
}
