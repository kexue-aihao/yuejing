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
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => Hash::make('password'), 'role' => 'admin', 'email_verified_at' => now()],
        );

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password'), 'role' => 'user', 'email_verified_at' => now()],
        );

        foreach ([['name' => '玄幻', 'slug' => 'xuanhuan'], ['name' => '都市', 'slug' => 'dushi'], ['name' => '科幻', 'slug' => 'kehuan']] as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
