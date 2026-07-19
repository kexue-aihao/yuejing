<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserTwoFactorSetting;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesYuejingData;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use CreatesYuejingData;
    use RefreshDatabase;

    public function test_web_setup_renders_scan_import_qr_code_with_yuejing_issuer(): void
    {
        $user = User::factory()->create();
        $setup = app(TwoFactorService::class)->enable($user);

        $this->actingAs($user)
            ->withSession(['two_factor_setup' => $setup])
            ->get(route('two-factor.show'))
            ->assertOk()
            ->assertSee('data-vue-two-factor-qr')
            ->assertSee('otpauth://totp/', false)
            ->assertSee('issuer=%E9%98%85%E5%A2%83', false);
    }

    public function test_setup_returns_secret_and_recovery_codes_without_enabling_two_factor(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJsonWithCsrf(route('two-factor.enable'));

        $response->assertCreated()
            ->assertJsonPath('enabled', false)
            ->assertJsonStructure(['secret', 'recovery_codes']);
        $response->assertJsonStructure(['otpauth_uri']);
        $this->assertStringStartsWith('otpauth://totp/', $response->json('otpauth_uri'));
        $this->assertStringContainsString('issuer=%E9%98%85%E5%A2%83', $response->json('otpauth_uri'));
        $this->assertStringContainsString('secret='.$response->json('secret'), $response->json('otpauth_uri'));
        $this->assertCount(8, $response->json('recovery_codes'));
        $this->assertDatabaseHas('user_two_factor_settings', [
            'user_id' => $user->id,
            'enabled' => false,
        ]);
        $this->assertNotNull($user->fresh()->twoFactorSetting->secret);
    }

    public function test_setup_can_be_confirmed_with_a_valid_totp_and_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create();
        $setup = $this->actingAs($user)->postJsonWithCsrf(route('two-factor.enable'))->json();

        $this->actingAs($user)
            ->postJsonWithCsrf(route('two-factor.enable'), ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertFalse($user->fresh()->twoFactorSetting->enabled);

        $this->actingAs($user)
            ->postJsonWithCsrf(route('two-factor.enable'), ['code' => $this->totpCode($setup['secret'])])
            ->assertOk()
            ->assertJson(['message' => '二步验证已启用。']);
        $this->assertTrue($user->fresh()->twoFactorSetting->enabled);
        $this->assertNotNull($user->fresh()->twoFactorSetting->confirmed_at);
    }

    public function test_enabled_account_requires_totp_challenge_after_password_login(): void
    {
        $user = $this->enableTwoFactorForUser();

        $login = $this->postJsonWithCsrf(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $login->assertAccepted()
            ->assertJsonPath('two_factor_required', true);
        $this->assertGuest();
        $this->assertSame($user->id, session('pending_two_factor_user_id'));

        $this->getJson(route('two-factor.challenge'))
            ->assertOk()
            ->assertJson(['message' => __('ui.messages.two_factor_setup')]);

        $this->postJsonWithCsrf(route('two-factor.challenge'), [
            'code' => $this->totpCode($this->setupSecret($user)),
        ])->assertOk()->assertJson(['message' => __('ui.messages.login_success')]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_authenticates_once_and_is_consumed(): void
    {
        $user = $this->enableTwoFactorForUser();
        $setting = $user->fresh()->twoFactorSetting;
        $recoveryCode = $this->recoveryCodes($setting)[0];

        $this->postJsonWithCsrf(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertAccepted();
        $this->postJsonWithCsrf(route('two-factor.challenge'), [
            'recovery_code' => $recoveryCode,
        ])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $this->recoveryCodes($user->fresh()->twoFactorSetting));

        $this->postJsonWithCsrf(route('logout'))->assertOk();
        $this->postJsonWithCsrf(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertAccepted();
        $this->postJsonWithCsrf(route('two-factor.challenge'), [
            'recovery_code' => $recoveryCode,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_two_factor_can_be_disabled_with_current_password(): void
    {
        $user = $this->enableTwoFactorForUser();

        $this->actingAs($user)
            ->deleteJsonWithCsrf(route('two-factor.disable'), ['current_password' => 'password'])
            ->assertOk()
            ->assertJson(['message' => '二步验证已禁用。']);
        $this->assertFalse($user->fresh()->twoFactorSetting->enabled);
        $this->assertNull($user->fresh()->twoFactorSetting->secret);
    }

    private function enableTwoFactorForUser(): User
    {
        $user = User::factory()->create(['password' => 'password']);
        $setup = $this->actingAs($user)->postJsonWithCsrf(route('two-factor.enable'))->json();
        $this->actingAs($user)->postJsonWithCsrf(route('two-factor.enable'), [
            'code' => $this->totpCode($setup['secret']),
        ])->assertOk();

        return $user->fresh();
    }

    private function setupSecret(User $user): string
    {
        $setting = $user->twoFactorSetting;
        $property = new \ReflectionProperty($setting, 'attributes');
        $property->setAccessible(true);
        $attributes = $property->getValue($setting);
        return \Illuminate\Support\Facades\Crypt::decryptString($attributes['secret']);
    }

    private function recoveryCodes(UserTwoFactorSetting $setting): array
    {
        $property = new \ReflectionProperty($setting, 'attributes');
        $property->setAccessible(true);
        $attributes = $property->getValue($setting);
        return json_decode(\Illuminate\Support\Facades\Crypt::decryptString($attributes['recovery_codes']), true, 512, JSON_THROW_ON_ERROR);
    }
}
