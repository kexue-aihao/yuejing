<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_seed_does_not_create_the_known_test_account(): void
    {
        $this->artisan('db:seed', ['--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('categories', ['slug' => 'xuanhuan']);
        $this->assertDatabaseHas('categories', ['slug' => 'dushi']);
        $this->assertDatabaseHas('categories', ['slug' => 'kehuan']);
    }
}
