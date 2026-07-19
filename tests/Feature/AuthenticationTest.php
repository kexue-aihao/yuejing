<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_and_logs_in_a_user_without_external_mail_delivery(): void
    {
        Notification::fake();
        Setting::create([
            'key' => 'email_verification_required',
            'value' => '1',
            'type' => 'boolean',
        ]);

        $response = $this->postWithCsrf(route('register'), [
            'name' => '新读者',
            'email' => 'reader@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'reader@example.test')->firstOrFail();
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '新读者',
            'role' => 'user',
        ]);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_without_email_verification_does_not_send_notification(): void
    {
        Notification::fake();

        $response = $this->postWithCsrf(route('register'), [
            'name' => '免验证读者',
            'email' => 'no-verification@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        Notification::assertNothingSent();
    }

    public function test_registration_email_failure_does_not_return_server_error(): void
    {
        Setting::create([
            'key' => 'email_verification_required',
            'value' => '1',
            'type' => 'boolean',
        ]);
        config(['mail.default' => 'smtp']);
        config(['mail.mailers.smtp.host' => '127.0.0.1']);
        config(['mail.mailers.smtp.port' => 1]);

        $response = $this->postWithCsrf(route('register'), [
            'name' => '邮件失败读者',
            'email' => 'mail-failure@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'mail-failure@example.test']);
        $this->assertAuthenticated();
    }

    public function test_registration_can_create_an_author_without_exposing_privileged_roles(): void
    {
        $this->postJsonWithCsrf(route('register'), [
            'name' => '投稿作者',
            'email' => 'author@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'author',
        ])->assertCreated()->assertJsonPath('user.role', 'author');

        $this->assertDatabaseHas('users', [
            'email' => 'author@example.test',
            'role' => 'author',
        ]);
    }

    public function test_registration_defaults_to_user_when_role_is_omitted(): void
    {
        $this->postJsonWithCsrf(route('register'), [
            'name' => '默认读者',
            'email' => 'default-user@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertCreated()->assertJsonPath('user.role', 'user');

        $this->assertDatabaseHas('users', [
            'email' => 'default-user@example.test',
            'role' => 'user',
        ]);
    }

    public function test_registration_rejects_privileged_or_unknown_roles(): void
    {
        foreach (['editor', 'admin', 'moderator'] as $role) {
            $this->from(route('register'))
                ->postWithCsrf(route('register'), [
                    'name' => '非法角色',
                    'email' => $role.'@example.test',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                    'role' => $role,
                ])
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors('role');

            $this->assertDatabaseMissing('users', ['email' => $role.'@example.test']);
        }
    }

    public function test_login_authenticates_valid_credentials_and_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => 'password',
        ]);

        $this->from(route('login'))
            ->postWithCsrf(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->postWithCsrf(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_logout_returns_homepage_in_guest_state(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-auth-state="authenticated"', false);

        $this->postWithCsrf(route('logout'))
            ->assertRedirect(route('home'))
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('CDN-Cache-Control', 'no-store')
            ->assertHeader('Cloudflare-CDN-Cache-Control', 'no-store');

        $this->assertGuest();
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-auth-state="authenticated"', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_authenticated_state_endpoint_cannot_be_cached(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/auth/me', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Vary', 'Cookie');
    }

    public function test_email_verification_requirement_blocks_unverified_submission_pages_when_enabled(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'author']);
        Setting::create([
            'key' => 'email_verification_required',
            'value' => '1',
            'type' => 'boolean',
        ]);

        $this->actingAs($user)
            ->get(route('author.submissions'))
            ->assertForbidden()
            ->assertSee(__('ui.messages.email_verification_required'))
            ->assertDontSee('ui.messages.email_verification_required');
    }

    public function test_email_verification_requirement_allows_unverified_submission_pages_when_disabled(): void
    {
        Setting::query()->where('key', 'email_verification_required')->delete();
        $user = User::factory()->unverified()->create(['role' => 'author']);
        Setting::create([
            'key' => 'email_verification_required',
            'value' => '0',
            'type' => 'boolean',
        ]);

        $this->actingAs($user)
            ->get(route('author.submissions'))
            ->assertOk()
            ->assertViewIs('pages.author.submissions');
    }

    public function test_verification_link_marks_an_unverified_user_as_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinute(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($url)
            ->assertOk()
            ->assertJson(['message' => __('ui.messages.email_verified')]);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
