<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Novel;
use App\Models\ReadingRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicController extends Controller
{
    public function home(Request $request)
    {
        if (! $this->wantsJson($request)) {
            return view('welcome');
        }

        return response()->json($this->novelPaginator($request));
    }

    public function index(Request $request)
    {
        $paginator = $this->novelPaginator($request);

        if ($this->wantsJson($request)) {
            return response()->json($paginator);
        }

        $novels = $paginator->getCollection()->map(fn (Novel $novel) => $this->novelArray($novel));

        return view('pages.novels.index', compact('novels'));
    }

    public function novel(Request $request, Novel $novel)
    {
        abort_unless($novel->status === 'published', 404);
        $novel->increment('views_count');
        $novel->load(['author:id,name', 'categories', 'chapters:id,novel_id,chapter_number,title,status,published_at']);

        if (! $this->wantsJson($request)) {
            return view('pages.novels.show', [
                'novel' => $this->novelArray($novel),
                'isFavorited' => auth()->check() && $novel->favorites()->where('user_id', auth()->id())->exists(),
                'chapters' => $novel->chapters
                    ->where('status', 'published')
                    ->map(fn (Chapter $chapter) => [
                        'number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'date' => $chapter->published_at?->format('m-d') ?? '待更新',
                    ])->values(),
            ]);
        }

        return response()->json($novel->loadCount('ratings'));
    }

    public function chapter(Request $request, Novel $novel, Chapter $chapter)
    {
        abort_unless($chapter->novel_id === $novel->id && $novel->status === 'published' && $chapter->status === 'published', 404);

        $record = null;
        if (auth()->check()) {
            $record = ReadingRecord::updateOrCreate(
                ['user_id' => auth()->id(), 'novel_id' => $novel->id],
                ['chapter_id' => $chapter->id, 'progress' => 100, 'last_read_at' => now()],
            );
        }

        if (! $this->wantsJson($request)) {
            return view('pages.novels.read', [
                'novel' => $this->novelArray($novel->load('author:id,name')),
                'chapter' => [
                    'number' => $chapter->chapter_number,
                    'title' => $chapter->title,
                    'content' => $chapter->content,
                ],
            ]);
        }

        return response()->json(['chapter' => $chapter, 'reading_record' => $record]);
    }

    private function novelPaginator(Request $request)
    {
        if (! Schema::hasTable('novels')) {
            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                0,
                config('yuejing.pagination'),
                1,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        }

        return Novel::query()->with(['author:id,name', 'categories:id,name'])
            ->where('status', 'published')
            ->when($request->string('q')->toString(), fn ($query, $q) => $query->where('title', 'like', "%{$q}%"))
            ->when($request->string('genre')->toString(), fn ($query, $genre) => $query->whereHas('categories', fn ($categories) => $categories->where('name', 'like', "%{$genre}%")))
            ->when($request->string('sort')->toString() === 'hot', fn ($query) => $query->orderByDesc('views_count'))
            ->when($request->string('sort')->toString() !== 'hot', fn ($query) => $query->latest('published_at'))
            ->paginate(config('yuejing.pagination'));
    }

    private function novelArray(Novel $novel): array
    {
        $chaptersCount = $novel->relationLoaded('chapters')
            ? $novel->chapters->where('status', 'published')->count()
            : $novel->chapters()->where('status', 'published')->count();

        return [
            'id' => $novel->id,
            'title' => $novel->title,
            'slug' => $novel->slug,
            'author' => $novel->author?->name ?? '匿名作者',
            'genre' => $novel->categories?->pluck('name')->join(' · ') ?: '现代 · 故事',
            'desc' => $novel->synopsis ?? '作者还没有留下简介，但故事已经准备好被打开。',
            'description' => $novel->synopsis,
            'chapters' => $chaptersCount,
            'chapters_count' => $chaptersCount,
            'status' => $novel->status === 'published' ? '连载中' : $novel->status,
            'cover_url' => $novel->cover_url,
            'cover_a' => '#355c5d',
            'cover_b' => '#d6aa67',
        ];
    }
}
