<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $requestedSection = $request->query('section');
        $activeSection = in_array($requestedSection, ['messages', 'groups'], true)
            ? $requestedSection
            : ($user->isRole(['author', 'editor', 'admin']) && $requestedSection === 'submissions'
                ? 'submissions'
                : 'dashboard');

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

        $submissionHistory = null;
        $categories = collect();
        if ($activeSection === 'submissions') {
            $submissionHistory = $user->submissions()
                ->with('reviewer:id,name')
                ->latest()
                ->paginate(config('yuejing.pagination'))
                ->withQueryString();
            $categories = Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);
        }

        return view('pages.dashboard', [
            'reading' => $reading,
            'favorites' => $favorites,
            'submissions' => $submissions,
            'favoriteCount' => $user->favorites()->count(),
            'readingCount' => $user->readingRecords()->count(),
            'submissionCounts' => $submissionCounts,
            'submissionHistory' => $submissionHistory,
            'categories' => $categories,
            'activeSection' => $activeSection,
            'messagesApi' => [
                'users' => url('/api/messages/users'),
                'index' => url('/api/messages'),
                'store' => url('/api/messages'),
                'show' => url('/api/messages'),
                'read' => url('/api/messages'),
                'stream' => url('/api/messages'),
            ],
            'groupsApi' => [
                'users' => url('/api/messages/users'),
                'index' => url('/api/groups'),
                'store' => url('/api/groups'),
                'show' => url('/api/groups'),
                'addMember' => url('/api/groups'),
                'removeMember' => url('/api/groups'),
                'sendMessage' => url('/api/groups'),
                'read' => url('/api/groups'),
                'stream' => url('/api/groups'),
            ],
            'currentUserId' => $user->id,
        ]);
    }

    private function readingItem($record): array
    {
        return [
            'title' => $record->novel?->title ?? __('ui.components.untitled_work'),
            'author' => $record->novel?->author?->name ?? __('ui.components.anonymous_author'),
            'progress' => __('ui.account_pages.chapter', ['number' => $record->chapter?->chapter_number ?? 1]),
            'status' => __('ui.account_pages.read_to', ['percent' => (int) $record->progress]),
            'slug' => $record->novel?->slug,
            'chapter' => $record->chapter?->chapter_number ?? 1,
            'last_read_at' => $record->last_read_at,
        ];
    }
}
