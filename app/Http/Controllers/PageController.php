<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $reading = $user->readingRecords()
            ->with(['novel.author:id,name', 'chapter:id,title,chapter_number'])
            ->latest('last_read_at')
            ->limit(5)
            ->get()
            ->map(fn ($record) => $this->readingItem($record));

        $favorites = $user->favorites()
            ->with('novel.author:id,name')
            ->whereHas('novel', fn ($query) => $query->where('status', 'published'))
            ->latest()
            ->limit(6)
            ->get();

        $submissions = $user->submissions()->latest()->limit(5)->get();
        $submissionCounts = $user->submissions()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('pages.dashboard', [
            'reading' => $reading,
            'favorites' => $favorites,
            'submissions' => $submissions,
            'favoriteCount' => $user->favorites()->count(),
            'readingCount' => $user->readingRecords()->count(),
            'submissionCounts' => $submissionCounts,
        ]);
    }

    private function readingItem($record): array
    {
        return [
            'title' => $record->novel?->title ?? '未命名作品',
            'author' => $record->novel?->author?->name ?? '匿名作者',
            'progress' => '第 '.($record->chapter?->chapter_number ?? 1).' 章',
            'status' => '阅读至 '.((int) $record->progress).'%',
            'slug' => $record->novel?->slug,
            'chapter' => $record->chapter?->chapter_number ?? 1,
            'last_read_at' => $record->last_read_at,
        ];
    }
}
