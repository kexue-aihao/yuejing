<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = strtolower(trim((string) env('YUEJING_ADMIN_EMAIL', '')));
        $adminPassword = (string) env('YUEJING_ADMIN_PASSWORD', '');

        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) && strlen($adminPassword) >= 12) {
            User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => env('YUEJING_ADMIN_NAME', '网站管理员'),
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'email_verified_at' => now(),
                ],
            );
        }

        foreach ([['name' => '玄幻', 'slug' => 'xuanhuan'], ['name' => '都市', 'slug' => 'dushi'], ['name' => '科幻', 'slug' => 'kehuan']] as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
