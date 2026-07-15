<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('yuejing:publish-drafts', function () {
    $count = \App\Models\Novel::where('status', 'draft')->whereNotNull('published_at')->update(['status' => 'published']);
    $this->info("Published {$count} novels.");
})->purpose('Publish scheduled novels whose publication time has arrived.');
