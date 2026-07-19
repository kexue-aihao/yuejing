<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class EnvironmentConfigService
{
    private const SECRET_KEYS = ['APP_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD'];

    private const DEFINITIONS = [
        'APP_NAME' => ['type' => 'text', 'config' => 'app.name', 'description' => 'app_name'],
        'APP_ENV' => ['type' => 'text', 'config' => 'app.env', 'description' => 'app_env'],
        'APP_URL' => ['type' => 'url', 'config' => 'app.url', 'description' => 'app_url'],
        'APP_KEY' => ['type' => 'secret', 'config' => 'app.key', 'description' => 'app_key'],
        'APP_LOCALE' => ['type' => 'text', 'config' => 'app.locale', 'description' => 'app_locale'],
        'APP_FALLBACK_LOCALE' => ['type' => 'text', 'config' => 'app.fallback_locale', 'description' => 'app_fallback_locale'],
        'DB_CONNECTION' => ['type' => 'text', 'config' => 'database.default', 'description' => 'db_connection'],
        'DB_HOST' => ['type' => 'text', 'config' => 'database.host', 'description' => 'db_host'],
        'DB_DATABASE' => ['type' => 'text', 'config' => 'database.database', 'description' => 'db_database'],
        'DB_USERNAME' => ['type' => 'text', 'config' => 'database.username', 'description' => 'db_username'],
        'DB_PASSWORD' => ['type' => 'secret', 'config' => 'database.password', 'description' => 'db_password'],
        'SESSION_DRIVER' => ['type' => 'text', 'config' => 'session.driver', 'description' => 'session_driver'],
        'SESSION_LIFETIME' => ['type' => 'number', 'config' => 'session.lifetime', 'description' => 'session_lifetime'],
        'SESSION_ENCRYPT' => ['type' => 'boolean', 'config' => 'session.encrypt', 'description' => 'session_encrypt'],
        'CACHE_STORE' => ['type' => 'text', 'config' => 'cache.default', 'description' => 'cache_store'],
        'QUEUE_CONNECTION' => ['type' => 'text', 'config' => 'queue.default', 'description' => 'queue_connection'],
        'FILESYSTEM_DISK' => ['type' => 'text', 'config' => 'filesystems.default', 'description' => 'filesystem_disk'],
        'MAIL_MAILER' => ['type' => 'text', 'config' => 'mail.default', 'description' => 'mail_mailer'],
        'MAIL_HOST' => ['type' => 'text', 'config' => 'mail.host', 'description' => 'mail_host'],
        'MAIL_USERNAME' => ['type' => 'text', 'config' => 'mail.username', 'description' => 'mail_username'],
        'MAIL_PASSWORD' => ['type' => 'secret', 'config' => 'mail.password', 'description' => 'mail_password'],
        'MAIL_FROM_ADDRESS' => ['type' => 'email', 'config' => 'mail.from.address', 'description' => 'mail_from_address'],
        'YUEJING_EMAIL_VERIFICATION_REQUIRED' => ['type' => 'boolean', 'config' => 'yuejing.email_verification.required', 'description' => 'email_verification'],
        'YUEJING_PAGINATION' => ['type' => 'number', 'config' => 'yuejing.pagination', 'description' => 'pagination'],
        'YUEJING_TOTP_PERIOD' => ['type' => 'number', 'config' => 'yuejing.two_factor.totp_period', 'description' => 'totp_period'],
        'YUEJING_TOTP_WINDOW' => ['type' => 'number', 'config' => 'yuejing.two_factor.totp_window', 'description' => 'totp_window'],
        'YUEJING_TOTP_CHALLENGE_LIFETIME' => ['type' => 'number', 'config' => 'yuejing.two_factor.challenge_lifetime', 'description' => 'totp_lifetime'],
        'YUEJING_TOTP_MAX_ATTEMPTS' => ['type' => 'number', 'config' => 'yuejing.two_factor.max_attempts', 'description' => 'totp_attempts'],
    ];

    public function items(): array
    {
        return collect(self::DEFINITIONS)->map(function (array $definition, string $key): array {
            $current = $this->currentValue($key, $definition['config']);
            $isBoolean = $definition['type'] === 'boolean';
            $isSecret = $definition['type'] === 'secret';

            return [
                'key' => $key,
                'value' => $isSecret ? $this->configuredValue($current) : ($isBoolean ? $this->booleanValue($current) : $this->displayValue($current)),
                'current' => $isSecret ? '' : ($isBoolean ? (bool) $current : $this->inputValue($current)),
                'type' => $definition['type'],
                'checked' => $isBoolean ? (bool) $current : null,
                'description' => __('ui.admin.env_config_descriptions.'.$definition['description']),
            ];
        })->values()->all();
    }

    public function update(array $values): void
    {
        $path = base_path('.env');
        if (! is_file($path) || ! is_writable($path)) {
            throw new RuntimeException(__('ui.messages.environment_file_not_writable'));
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException(__('ui.messages.environment_file_not_readable'));
        }

        foreach ($values as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, self::DEFINITIONS)) {
                throw new RuntimeException(__('ui.messages.environment_key_invalid'));
            }
            if (in_array($key, self::SECRET_KEYS, true) && trim((string) $value) === '') {
                continue;
            }

            $line = $key.'='.$this->quote((string) $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace($pattern, $line, $contents, 1) ?? $contents;
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException(__('ui.messages.environment_file_not_writable'));
        }

        Artisan::call('config:clear');
    }

    public function isEditable(string $key): bool
    {
        return array_key_exists($key, self::DEFINITIONS);
    }

    private function currentValue(string $key, string $configKey): mixed
    {
        return match ($key) {
            'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD' => config('database.connections.'.config('database.default').'.'.strtolower(str_replace('DB_', '', $key))),
            'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD' => config('mail.mailers.'.config('mail.default').'.'.strtolower(str_replace('MAIL_', '', $key))),
            default => config($configKey),
        };
    }

    private function inputValue(mixed $value): string
    {
        return is_scalar($value) && $value !== null ? (string) $value : '';
    }

    private function displayValue(mixed $value): string
    {
        return filled($value) ? $this->inputValue($value) : __('ui.admin.not_configured');
    }

    private function configuredValue(mixed $value): string
    {
        return filled($value) ? __('ui.admin.configured') : __('ui.admin.not_configured');
    }

    private function booleanValue(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? __('ui.admin.enabled') : __('ui.admin.disabled');
    }

    private function quote(string $value): string
    {
        if ($value === '') {
            return '""';
        }
        if (preg_match('/[\s#"\']/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
