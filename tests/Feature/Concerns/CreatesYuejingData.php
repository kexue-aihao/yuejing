<?php

namespace Tests\Feature\Concerns;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\User;
use Illuminate\Support\Carbon;

trait CreatesYuejingData
{
    protected function createPublishedNovel(?User $author = null, array $attributes = []): Novel
    {
        $author ??= User::factory()->create(['role' => 'author']);

        $novel = Novel::create(array_merge([
            'author_id' => $author->id,
            'title' => '潮汐之上',
            'slug' => 'chaoxi-zhi-shang',
            'synopsis' => '她在潮汐里寻找失散多年的答案。',
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
        ], $attributes));

        $novel->chapters()->createMany([
            [
                'chapter_number' => 1,
                'title' => '潮声从远处来',
                'content' => "海风从旧码头吹来。\n她手里攥着一封没有署名的信。",
                'status' => 'published',
                'published_at' => Carbon::now()->subDay(),
            ],
            [
                'chapter_number' => 2,
                'title' => '灯塔下的信',
                'content' => '她终于推开了旧书店的门。',
                'status' => 'published',
                'published_at' => Carbon::now(),
            ],
            [
                'chapter_number' => 3,
                'title' => '尚未抵达的夏天',
                'content' => '这一章还没有准备好被读者看到。',
                'status' => 'draft',
                'published_at' => null,
            ],
        ]);

        return $novel->fresh();
    }

    protected function createCategoryFor(Novel $novel, string $name = '都市情感'): Category
    {
        $category = Category::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
        ]);
        $novel->categories()->attach($category);

        return $category;
    }

    protected function totpCode(string $secret, ?int $timestamp = null): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }

        $secretBytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $secretBytes .= chr(bindec($chunk));
            }
        }

        $counter = intdiv($timestamp ?? time(), (int) config('yuejing.two_factor.totp_period', 30));
        $counterBytes = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $counterBytes, $secretBytes, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
