<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountNavigationContractTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('accountPages')]
    public function test_reader_navigation_hides_submission_link(string $routeName): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'user']))->get(route($routeName));

        $this->assertNavigationContract($response->getContent(), false);
        if ($routeName === 'dashboard') {
            $response->assertDontSee('作者中心')->assertDontSee('作品投稿');
        }
    }

    #[DataProvider('accountPages')]
    public function test_author_navigation_shows_submission_link(string $routeName): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'author']))->get(route($routeName));

        $this->assertNavigationContract($response->getContent(), true);
        if ($routeName === 'dashboard') {
            $response->assertSee('作品投稿')->assertDontSee('作者中心');
        }
    }

    #[DataProvider('elevatedRoles')]
    public function test_elevated_roles_can_see_submission_link(string $role): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => $role]))->get(route('account.settings'));

        $this->assertNavigationContract($response->getContent(), true);
    }

    public static function accountPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'favorites' => ['account.favorites'],
            'reading records' => ['account.reading-records'],
            'settings' => ['account.settings'],
        ];
    }

    public static function elevatedRoles(): array
    {
        return [
            'author' => ['author'],
            'editor' => ['editor'],
            'admin' => ['admin'],
        ];
    }

    private function assertNavigationContract(string $html, bool $canSubmit): void
    {
        $this->assertMatchesRegularExpression('/<nav class="dashboard-nav" aria-label="个人中心导航">(.*?)<\/nav>/s', $html, 'Shared account navigation must be rendered.');
        preg_match('/<nav class="dashboard-nav" aria-label="个人中心导航">(.*?)<\/nav>/s', $html, $matches);
        $navigation = $matches[1] ?? '';

        $this->assertStringContainsString('阅读概览', $navigation);
        $this->assertStringContainsString('我的收藏', $navigation);
        $this->assertStringContainsString('阅读记录', $navigation);
        $this->assertStringContainsString('账号设置', $navigation);
        $this->assertSame($canSubmit, str_contains($navigation, '作品投稿'));
        $this->assertStringNotContainsString('作者中心', $navigation);
        $this->assertSame(1, preg_match('/<a class="is-active"[^>]*aria-current="page"/', $navigation));
    }
}
