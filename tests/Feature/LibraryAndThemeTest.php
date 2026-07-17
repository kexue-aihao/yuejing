<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryAndThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_library_does_not_render_placeholder_books(): void
    {
        $response = $this->get(route('novels.index'));

        $response->assertOk()
            ->assertViewIs('pages.novels.index')
            ->assertSee('书架还是空的')
            ->assertDontSee('chaoxi-zhi-shang')
            ->assertDontSee('changan-you-xue')
            ->assertDontSee('星河失物招领处');
    }

    public function test_theme_toggle_exposes_eye_care_mode(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme-action="light"', false)
            ->assertSee('data-theme-action="dark"', false)
            ->assertSee('data-theme-action="eye-care"', false)
            ->assertSee('护眼');
    }
}