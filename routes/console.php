<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('yuejing:publish-drafts', function () {
    $count = \App\Models\Novel::where('status', 'draft')->whereNotNull('published_at')->update(['status' => 'published']);
    $this->info("Published {$count} novels.");
})->purpose('Publish scheduled novels whose publication time has arrived.');

Artisan::command('yuejing:admin {--reset-password : Explicitly replace the existing administrator password}', function () {
    $name = trim((string) config('yuejing.admin.name', ''));
    $email = strtolower(trim((string) config('yuejing.admin.email', '')));
    $password = (string) config('yuejing.admin.password', '');

    if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Set YUEJING_ADMIN_NAME and a valid YUEJING_ADMIN_EMAIL in .env first.');
        return 1;
    }

    if ($password !== '' && (strlen($password) < 12 || $password === 'password')) {
        $this->error('YUEJING_ADMIN_PASSWORD must be at least 12 characters and must not be the default password.');
        return 1;
    }

    $user = User::query()->where('email', $email)->first();
    $created = false;

    if (! $user) {
        if ($password === '') {
            $this->error('YUEJING_ADMIN_PASSWORD is required when creating a new administrator.');
            return 1;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $created = true;
    } else {
        $user->forceFill(['name' => $name, 'role' => 'admin', 'email_verified_at' => $user->email_verified_at ?? now()]);
        if ($this->option('reset-password')) {
            if ($password === '') {
                $this->error('YUEJING_ADMIN_PASSWORD is required with --reset-password.');
                return 1;
            }
            $user->password = Hash::make($password);
        }
        $user->save();
    }

    AuditLog::create([
        'user_id' => $user->id,
        'action' => $created ? 'admin.initialized' : 'admin.synchronized',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'metadata' => ['password_reset' => (bool) $this->option('reset-password')],
    ]);

    $this->info($created ? "Administrator {$email} created." : "Administrator {$email} synchronized.");
    return 0;
})->purpose('Create or synchronize the env-configured Yuejing administrator.');
