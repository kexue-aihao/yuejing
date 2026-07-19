<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverDeliveryTest extends TestCase
{
    public function test_public_cover_disk_uses_a_web_root_and_relative_url(): void
    {
        $this->assertSame(public_path('storage'), config('filesystems.disks.public.root'));
        $this->assertSame('/storage', config('filesystems.disks.public.url'));

        Storage::fake('public');
        Storage::disk('public')->put('covers/visible.jpg', 'cover');

        $this->assertSame('/storage/covers/visible.jpg', Storage::disk('public')->url('covers/visible.jpg'));
    }

    public function test_book_cover_ignores_missing_local_files_and_normalizes_existing_urls(): void
    {
        Storage::fake('public');

        $missing = view('components.book-cover', [
            'book' => [
                'title' => 'Missing cover',
                'author' => 'Author',
                'cover_url' => '/storage/covers/missing.jpg',
            ],
        ])->render();

        $this->assertStringNotContainsString('<img', $missing);
        $this->assertStringContainsString('cover-title', $missing);

        Storage::disk('public')->put('covers/visible.jpg', 'cover');
        $available = view('components.book-cover', [
            'book' => [
                'title' => 'Visible cover',
                'author' => 'Author',
                'cover_url' => 'https://old.example.test/storage/covers/visible.jpg',
            ],
        ])->render();

        $this->assertStringContainsString('src="http://localhost/storage/covers/visible.jpg"', $available);
        $this->assertStringContainsString('onerror=', $available);
    }
}
