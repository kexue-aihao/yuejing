<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The dashboard is an authenticated personal-center page, not an admin page.
     */
    public function test_dashboard_requires_authentication_and_is_available_to_known_roles(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect('/login');

        foreach (['user', 'author', 'editor', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertViewIs('pages.dashboard');

            $this->flushSession();
        }
    }

    /**
     * Ordinary readers must not receive an author-center entry point or access
     * the author submission page directly. Authoring roles may access it and
     * are redirected into the embedded dashboard section.
     */
    public function test_submission_page_is_limited_to_authoring_roles(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('author.submissions'))
            ->assertForbidden();

        foreach (['author', 'editor', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('author.submissions'))
                ->assertRedirect(route('dashboard', ['section' => 'submissions']));

            $this->flushSession();
        }
    }

    /**
     * The submission API must apply the same role boundary as the page.
     */
    public function test_submission_api_is_limited_to_authoring_roles(): void
    {
        $payload = [
            'cover_url' => 'https://example.test/covers/permissions.jpg',
            'title' => '权限矩阵投稿',
            'synopsis' => '权限测试简介',
            'manuscript' => '权限测试正文',
        ];

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJsonWithCsrf(route('author.submissions.store'), $payload)
            ->assertForbidden();

        foreach (['author', 'editor', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->postJsonWithCsrf(route('author.submissions.store'), $payload)
                ->assertCreated()
                ->assertJsonPath('submission.title', '权限矩阵投稿');

            $this->flushSession();
        }
    }

    /**
     * Every admin page and the corresponding admin API require the admin role.
     */
    public function test_admin_page_and_api_are_admin_only(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect('/login');

        foreach (['user', 'author', 'editor'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.dashboard'))
                ->assertForbidden();

            $this->actingAs($user)
                ->getJson('/api/admin')
                ->assertForbidden();

            $this->flushSession();
        }

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('pages.admin.dashboard');

        $this->actingAs($admin)
            ->getJson('/api/admin')
            ->assertOk()
            ->assertJsonStructure(['users', 'novels', 'chapters', 'pending_submissions']);
    }

    /**
     * An authenticated account with an unrecognized role must not bypass either
     * the author or admin authorization boundaries.
     */
    public function test_unknown_role_cannot_access_author_or_admin_surfaces(): void
    {
        $unknown = User::factory()->create(['role' => 'unknown']);

        $this->actingAs($unknown)
            ->get(route('dashboard'))
            ->assertOk();

        $this->actingAs($unknown)
            ->get(route('author.submissions'))
            ->assertForbidden();

        $this->actingAs($unknown)
            ->postJsonWithCsrf(route('author.submissions.store'), [
                'title' => '未知角色投稿',
                'manuscript' => '未知角色正文',
            ])
            ->assertForbidden();

        $this->actingAs($unknown)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($unknown)
            ->getJson('/api/admin')
            ->assertForbidden();
    }

    /**
     * Public registration may choose reader or author, but must never grant a
     * privileged role or silently accept an unrecognized role.
     *
     * @return array<string, array{string, int, string|null}>
     */
    public static function registrationRoleProvider(): array
    {
        return [
            'reader' => ['user', 201, 'user'],
            'author' => ['author', 201, 'author'],
            'editor cannot self-register' => ['editor', 422, null],
            'admin cannot self-register' => ['admin', 422, null],
            'unknown role is rejected' => ['unknown', 422, null],
        ];
    }

    #[DataProvider('registrationRoleProvider')]
    public function test_registration_role_matrix(string $requestedRole, int $expectedStatus, ?string $expectedStoredRole): void
    {
        $email = 'registration-'.strtolower($requestedRole).'@example.test';

        $response = $this->postJsonWithCsrf(route('auth.register'), [
                'name' => '权限矩阵注册用户',
                'email' => $email,
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => $requestedRole,
            ]);

        $response->assertStatus($expectedStatus);

        if ($expectedStoredRole !== null) {
            $response->assertJsonPath('user.role', $expectedStoredRole);
            $this->assertDatabaseHas('users', [
                'email' => $email,
                'role' => $expectedStoredRole,
            ]);

            return;
        }

        $response->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertGuest();
    }
}
