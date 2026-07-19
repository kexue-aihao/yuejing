<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class AppSettingService
{
    public function emailVerificationRequired(): bool
    {
        // The environment flag is the hard safety gate. A stale database
        // setting must never enable email verification by itself.
        if (! (bool) config('yuejing.email_verification.required', false)) {
            return false;
        }

        return filter_var($this->get('email_verification_required', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }

    public function set(string $key, mixed $value, ?int $userId = null): Setting
    {
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };

        $setting = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $type === 'json' ? json_encode($value) : (string) $value, 'type' => $type, 'updated_by' => $userId],
        );

        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => 'setting.updated',
            'auditable_type' => Setting::class,
            'auditable_id' => $setting->id,
            'metadata' => ['key' => $key],
        ]);

        return $setting;
    }
}
