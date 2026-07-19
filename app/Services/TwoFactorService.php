<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTwoFactorSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TwoFactorService
{
    /**
     * Generate and store an unconfirmed setup. The existing setting stays disabled
     * until confirmEnable() proves the user can generate a valid TOTP.
     */
    public function enable(User $user): array
    {
        $secret = $this->base32Encode(random_bytes(20));
        $recoveryCodes = $this->generateRecoveryCodes();

        UserTwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            [
                'enabled' => false,
                'secret' => Crypt::encryptString($secret),
                'recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
                'confirmed_at' => null,
            ],
        );

        return [
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
            'otpauth_uri' => $this->provisioningUri($user, $secret),
        ];
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = (string) config('yuejing.two_factor.issuer', '阅境');
        $account = trim((string) ($user->email ?: $user->name ?: $user->getKey()));
        $label = rawurlencode($issuer).':'.rawurlencode($account);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => max(1, (int) config('yuejing.two_factor.totp_period', 30)),
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    public function confirmEnable(User $user, string $code): bool
    {
        $setting = UserTwoFactorSetting::query()->where('user_id', $user->id)->first();

        if (! $setting || $setting->enabled || ! $setting->secret) {
            return false;
        }

        try {
            $secret = Crypt::decryptString($setting->secret);
        } catch (\Throwable) {
            return false;
        }

        if (! $this->verifyTotpSecret($secret, $code)) {
            return false;
        }

        $setting->forceFill(['enabled' => true, 'confirmed_at' => now()])->save();

        return true;
    }

    /**
     * Verify a TOTP or, when enabled, consume one matching recovery code.
     * Recovery-code consumption is locked so a code cannot be replayed concurrently.
     */
    public function verifyCode(User $user, string $code): array
    {
        $setting = UserTwoFactorSetting::query()->where('user_id', $user->id)->first();
        if (! $setting || ! $setting->enabled || ! $setting->secret) {
            return ['valid' => false, 'recovery_code' => false];
        }

        $normalized = strtoupper(trim($code));
        try {
            $secret = Crypt::decryptString($setting->secret);
        } catch (\Throwable) {
            return ['valid' => false, 'recovery_code' => false];
        }

        if ($this->verifyTotpSecret($secret, $normalized)) {
            return ['valid' => true, 'recovery_code' => false];
        }

        return DB::transaction(function () use ($user, $normalized): array {
            $lockedSetting = UserTwoFactorSetting::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedSetting || ! $lockedSetting->enabled || ! $lockedSetting->recovery_codes) {
                return ['valid' => false, 'recovery_code' => false];
            }

            try {
                $recoveryCodes = json_decode(
                    Crypt::decryptString($lockedSetting->recovery_codes),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            } catch (\Throwable) {
                return ['valid' => false, 'recovery_code' => false];
            }

            foreach ($recoveryCodes as $index => $recoveryCode) {
                if (! is_string($recoveryCode) || ! $this->secureStringEquals($recoveryCode, $normalized)) {
                    continue;
                }

                unset($recoveryCodes[$index]);
                $lockedSetting->forceFill([
                    'recovery_codes' => Crypt::encryptString(json_encode(array_values($recoveryCodes), JSON_THROW_ON_ERROR)),
                ])->save();

                return ['valid' => true, 'recovery_code' => true];
            }

            return ['valid' => false, 'recovery_code' => false];
        });
    }

    public function verifyTotp(User $user, string $code): bool
    {
        $setting = UserTwoFactorSetting::query()->where('user_id', $user->id)->first();
        if (! $setting || ! $setting->enabled || ! $setting->secret) {
            return false;
        }

        try {
            return $this->verifyTotpSecret(Crypt::decryptString($setting->secret), $code);
        } catch (\Throwable) {
            return false;
        }
    }

    public function disable(User $user): void
    {
        UserTwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => false, 'secret' => null, 'recovery_codes' => null, 'confirmed_at' => null],
        );
    }

    private function verifyTotpSecret(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', trim($code))) {
            return false;
        }

        $secretBytes = $this->base32Decode($secret);
        if ($secretBytes === '') {
            return false;
        }

        $period = max(1, (int) config('yuejing.two_factor.totp_period', 30));
        $window = max(0, (int) config('yuejing.two_factor.totp_window', 1));
        $timeCounter = intdiv(time(), $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if ($this->secureStringEquals($this->totp($secretBytes, $timeCounter + $offset), trim($code))) {
                return true;
            }
        }

        return false;
    }

    private function totp(string $secret, int $counter): string
    {
        $counterBytes = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $counterBytes, $secret, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');
        $bits = '';

        foreach (str_split($encoded) as $character) {
            $value = strpos($alphabet, $character);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (unpack('C*', $data) as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function generateRecoveryCodes(): array
    {
        return array_map(static function (): string {
            $value = strtoupper(bin2hex(random_bytes(4)));
            return substr($value, 0, 4).'-'.substr($value, 4, 4);
        }, range(1, 8));
    }

    private function secureStringEquals(string $known, string $given): bool
    {
        return strlen($known) === strlen($given) && hash_equals($known, $given);
    }
}
