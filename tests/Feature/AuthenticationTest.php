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

    public function test_email_verification_requirement_blocks_unverified_submission_pages_when_enabled(): void
    {
        $user = User::factory()->unverified()->create();
        Setting::create([
            'key' => 'email_verification_required',
            'value' => '1',
            'type' => 'boolean',
        ]);

        $this->actingAs($user)
            ->get(route('author.submissions'))
            ->assertForbidden();
    }

    public function test_email_verification_requirement_allows_unverified_submission_pages_when_disabled(): void
    {
        Setting::query()->where('key', 'email_verification_required')->delete();
        $user = User::factory()->unverified()->create();
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
            ->assertJson(['message' => 'Email verified successfully.']);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
