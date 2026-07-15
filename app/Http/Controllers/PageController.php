<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function dashboard(Request $request)
    {
        $reading = $request->user()->readingRecords()
            ->with(['novel.author:id,name', 'chapter:id,title,chapter_number'])
            ->latest('last_read_at')
            ->limit(10)
            ->get()
            ->map(fn ($record) => [
                'title' => $record->novel?->title ?? '未命名作品',
                'author' => $record->novel?->author?->name ?? '匿名作者',
                'progress' => '第 '.($record->chapter?->chapter_number ?? 1).' 章',
                'status' => '阅读至 '.((int) $record->progress).'%',
                'slug' => $record->novel?->slug,
            ]);

        return view('pages.dashboard', compact('reading'));
    }
}
