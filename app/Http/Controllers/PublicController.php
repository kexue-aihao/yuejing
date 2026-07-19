<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Category;
use App\Models\Novel;
use App\Models\ReadingRecord;
use App\Models\SearchEvent;
use App\Services\MarkdownRenderer;
use App\Services\RatingScale;
use App\Services\RecommendationService;
use App\Services\AppSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicController extends Controller
{
    public function home(Request $request, RecommendationService $recommendations)
    {
        if (! $this->wantsJson($request)) {
            $editorial = $this->homeEditorialData();

            return response()
                ->view('welcome', [
                    'recommendationUrl' => url('/api/recommendations/stream'),
                    'recommendations' => $recommendations->for($request->user(), $request, 6),
                    ...$editorial,
                ])
                ->withHeaders([
                    // The homepage is localized per session/cookie and must
                    // not be reused from a shared or stale HTML cache.
                    'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                    'CDN-Cache-Control' => 'no-store',
                    'Cloudflare-CDN-Cache-Control' => 'no-store',
                    'Surrogate-Control' => 'no-store',
                    'Vary' => 'Cookie, Accept-Language',
                ]);
        }

        return response()->json($this->novelPaginator($request));
    }

    private function homeEditorialData(): array
    {
        if (! Schema::hasTable('novels')) {
            return [
                'featured' => collect(),
                'newBooks' => collect(),
                'ranking' => collect(),
                'categories' => collect(),
                'homeStats' => ['works_count' => 0, 'readers_count' => 0],
            ];
        }

        $baseQuery = Novel::query()
            ->with(['author:id,name', 'categories:id,name'])
            ->where('status', 'published')
            ->withCount([
                'publishedChapters',
                'favorites',
                'activeRatings as reviews_count',
            ])
            ->withAvg('activeRatings', 'rating');

        $toBook = fn (Novel $novel): array => array_merge($this->novelArray($novel), [
            'desc' => $novel->synopsis,
            'score' => $novel->active_ratings_avg_rating !== null
                ? number_format((float) $novel->active_ratings_avg_rating, 1)
                : null,
            'tag' => $novel->categories->first()?->name,
        ]);

        $featured = (clone $baseQuery)
            ->orderByDesc('views_count')
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map($toBook)
            ->values();
        $newBooks = (clone $baseQuery)
            ->latest('published_at')
            ->limit(4)
            ->get()
            ->map($toBook)
            ->values();
        $ranking = (clone $baseQuery)
            ->whereHas('activeRatings')
            ->orderByDesc('active_ratings_avg_rating')
            ->orderByDesc('views_count')
            ->limit(5)
            ->get()
            ->map($toBook)
            ->values();
        $categories = Schema::hasTable('categories')
            ? Category::query()
                ->where('is_active', true)
                ->withCount(['novels' => fn ($query) => $query->where('status', 'published')])
                ->orderByDesc('novels_count')
                ->limit(5)
                ->get(['id', 'name', 'slug'])
            : collect();

        $readersCount = Schema::hasTable('reading_records')
            ? ReadingRecord::query()
                ->whereHas('novel', fn ($query) => $query->where('status', 'published'))
                ->distinct()
                ->count('user_id')
            : 0;
        $worksCount = Novel::where('status', 'published')->count();

        return [
            'featured' => $featured,
            'newBooks' => $newBooks,
            'ranking' => $ranking,
            'categories' => $categories,
            'homeStats' => [
                'works_count' => $worksCount,
                'readers_count' => $readersCount,
            ],
        ];
    }

    public function index(Request $request)
    {
        $paginator = $this->novelPaginator($request);
        $this->recordSearchEvent($request, $paginator->getCollection());

        if ($this->wantsJson($request)) {
            return response()->json($paginator);
        }

        $paginator
            ->through(fn (Novel $novel) => $this->novelArray($novel))
            ->appends($request->only(['q', 'genre', 'sort']));

        $categories = Schema::hasTable('categories')
            ? Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
            : collect();

        return view('pages.novels.index', [
            'novels' => $paginator,
            'categories' => $categories,
        ]);
    }

    public function categories(): \Illuminate\View\View
    {
        $categories = Schema::hasTable('categories')
            ? Category::query()
                ->where('is_active', true)
                ->withCount(['novels' => fn ($query) => $query->where('status', 'published')])
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description'])
            : collect();

        return view('pages.categories.index', compact('categories'));
    }

    public function about(): \Illuminate\View\View
    {
        return view('pages.info', ['page' => 'about']);
    }

    public function readingGuide(): \Illuminate\View\View
    {
        return view('pages.info', ['page' => 'reading-guide']);
    }

    public function contact(AppSettingService $settings): \Illuminate\View\View
    {
        $contactEmail = config('mail.from.address');

        if (Schema::hasTable('settings')) {
            $contactEmail = $settings->get('contact_email', $contactEmail);
        }

        return view('pages.info', [
            'page' => 'contact',
            'contactEmail' => is_string($contactEmail) ? trim($contactEmail) : '',
        ]);
    }

    public function novel(Request $request, Novel $novel)
    {
        abort_unless($novel->status === 'published', 404);
        if (! $this->wantsJson($request)) {
            $novel->increment('views_count');
        }
        $novel->load([
            'author:id,name',
            'categories',
            'chapters' => fn ($query) => $query
                ->where('status', 'published')
                ->select('id', 'novel_id', 'chapter_number', 'title', 'content', 'status', 'published_at', 'updated_at'),
        ])->loadCount([
            'publishedChapters',
            'favorites',
            'activeRatings as reviews_count',
        ]);
        $this->recordInterestEvent($request, $novel);

        $statistics = $this->novelStatistics($novel);
        $ratingScale = app(RatingScale::class);
        $activeRatings = $novel->activeRatings()
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get();
        $averageRating = $novel->activeRatings()->avg('rating');
        $currentRating = auth()->check()
            ? $novel->activeRatings()->where('user_id', auth()->id())->first()
            : null;

        if (! $this->wantsJson($request)) {
            return view('pages.novels.show', [
                'novel' => $this->novelArray($novel, $statistics),
                'novelModel' => $novel,
                'statistics' => $statistics,
                'isFavorited' => auth()->check() && $novel->favorites()->where('user_id', auth()->id())->exists(),
                'chapters' => $novel->chapters
                    ->where('status', 'published')
                    ->map(fn (Chapter $chapter) => [
                        'number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'date' => $chapter->published_at?->format('m-d') ?? __('ui.messages.pending_update'),
                    ])->values(),
                'ratings' => $activeRatings->map(fn ($rating) => [
                    'rating' => (float) $rating->rating,
                    'level' => $ratingScale->key($rating->rating),
                    'review' => $rating->review,
                    'criteria' => $rating->criteria ?? [],
                    'user' => $rating->user?->name,
                ])->values(),
                'averageRating' => $averageRating !== null ? round((float) $averageRating, 1) : null,
                'averageRatingLevel' => $averageRating !== null ? $ratingScale->key($averageRating) : null,
                'currentRating' => $currentRating,
            ]);
        }

        return response()->json([
            ...$this->novelArray($novel, $statistics),
            ...$statistics,
            'active_ratings' => $activeRatings->map(fn ($rating) => [
                'id' => $rating->id,
                'rating' => (float) $rating->rating,
                'review' => $rating->review,
                'criteria' => $rating->criteria ?? [],
                'user' => $rating->user?->name,
                'created_at' => $rating->created_at?->toISOString(),
            ])->values(),
            'statistics' => $statistics,
        ]);
    }

    public function reviews(Request $request, Novel $novel)
    {
        abort_unless($novel->status === 'published', 404);

        $novel->load([
            'chapters' => fn ($query) => $query
                ->where('status', 'published')
                ->select('id', 'novel_id', 'content', 'status', 'published_at', 'updated_at'),
        ])->loadCount([
            'publishedChapters',
            'favorites',
            'activeRatings as reviews_count',
        ]);

        $ratingScale = app(RatingScale::class);
        $statistics = $this->novelStatistics($novel);
        $ratings = $novel->activeRatings()
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get();
        $averageRating = $novel->activeRatings()->avg('rating');

        return response()->json([
            'statistics' => array_merge($statistics, [
                'average_rating' => $averageRating !== null ? round((float) $averageRating, 1) : null,
                'average_rating_level' => $averageRating !== null ? $ratingScale->key($averageRating) : null,
                'rating_count' => $statistics['reviews_count'],
            ]),
            'reviews' => $ratings->map(fn ($rating) => [
                'id' => $rating->id,
                'rating' => (float) $rating->rating,
                'level' => $ratingScale->key($rating->rating),
                'review' => $rating->review,
                'criteria' => $rating->criteria ?? [],
                'user' => $rating->user?->name,
                'created_at' => $rating->created_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function chapter(Request $request, Novel $novel, Chapter $chapter, MarkdownRenderer $markdownRenderer)
    {
        abort_unless($chapter->novel_id === $novel->id && $novel->status === 'published' && $chapter->status === 'published', 404);

        $record = null;
        if (auth()->check()) {
            $record = ReadingRecord::updateOrCreate(
                ['user_id' => auth()->id(), 'novel_id' => $novel->id],
                ['chapter_id' => $chapter->id, 'progress' => 100, 'last_read_at' => now()],
            );
        }
        $this->recordInterestEvent($request, $novel);

        if (! $this->wantsJson($request)) {
            return view('pages.novels.read', [
                'novel' => $this->novelArray($novel->load('author:id,name')),
                'chapter' => [
                    'number' => $chapter->chapter_number,
                    'title' => $chapter->title,
                    'content' => $chapter->content,
                    'content_html' => $markdownRenderer->render($chapter->content, $chapter->content_format ?? 'markdown'),
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

        $sort = $request->string('sort')->toString();
        $genre = trim(substr($request->string('genre')->toString(), 0, 160));

        return Novel::query()->with(['author:id,name', 'categories:id,name'])
            ->where('status', 'published')
            ->when(substr($request->string('q')->toString(), 0, 160), function ($query, $q): void {
                $query->where(function ($search) use ($q): void {
                    $search->where('title', 'like', "%{$q}%")
                        ->orWhere('synopsis', 'like', "%{$q}%")
                        ->orWhereHas('author', fn ($author) => $author->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('categories', fn ($category) => $category->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($genre !== '', fn ($query) => $query->whereHas('categories', function ($categories) use ($genre): void {
                $categories->where('slug', $genre)
                    ->orWhere('name', 'like', "%{$genre}%");
            }))
            ->when($sort === 'hot', fn ($query) => $query->orderByDesc('views_count'))
            ->when($sort !== 'hot', fn ($query) => $query->latest('published_at'))
            ->paginate(config('yuejing.pagination'))
            ->appends($request->only(['q', 'genre', 'sort']));
    }

    private function recordSearchEvent(Request $request, $novels): void
    {
        if (! Schema::hasTable('search_events')) {
            return;
        }

        $query = trim(substr($request->string('q')->toString(), 0, 160));
        $genre = trim(substr($request->string('genre')->toString(), 0, 160));
        if ($query === '' && $genre === '') {
            return;
        }

        $categories = collect();
        if ($genre !== '' && Schema::hasTable('categories')) {
            $categories = Category::query()
                ->where(function ($query) use ($genre): void {
                    $query->where('slug', $genre)
                        ->orWhere('name', 'like', "%{$genre}%");
                })
                ->limit(8)
                ->get();
        } else {
            $categories = $novels
                ->flatMap(fn (Novel $novel) => $novel->categories ?? [])
                ->unique('id')
                ->take(8)
                ->values();
        }

        $this->storeInterestEvents($request, $categories, $novels->first()?->id, $query !== '' ? $query : null);
    }

    private function recordInterestEvent(Request $request, Novel $novel): void
    {
        if (! Schema::hasTable('search_events')) {
            return;
        }

        $novel->loadMissing('categories:id');
        $this->storeInterestEvents($request, $novel->categories, $novel->id, null);
    }

    private function storeInterestEvents(Request $request, $categories, ?int $novelId, ?string $query): void
    {
        $timezone = $request->cookie('yuejing_timezone') ?: $request->user()?->timezone;
        $categories = collect($categories)->unique('id')->take(8)->values();
        $base = [
            'user_id' => $request->user()?->id,
            'query' => $query,
            'locale' => $request->attributes->get('display_locale', app()->getLocale()),
            'timezone' => is_string($timezone) ? $timezone : null,
            'session_hash' => hash('sha256', (string) $request->session()->getId()),
        ];

        if ($categories->isEmpty()) {
            SearchEvent::create([...$base, 'category_id' => null, 'novel_id' => $novelId]);
            return;
        }

        foreach ($categories as $category) {
            SearchEvent::create([...$base, 'category_id' => $category->id, 'novel_id' => $novelId]);
        }
    }

    private function novelArray(Novel $novel, ?array $statistics = null): array
    {
        $statistics ??= [
            'published_chapters_count' => $novel->published_chapters_count ?? ($novel->relationLoaded('chapters')
                ? $novel->chapters->where('status', 'published')->count()
                : $novel->chapters()->where('status', 'published')->count()),
            'favorites_count' => (int) ($novel->favorites_count ?? 0),
            'reviews_count' => (int) ($novel->reviews_count ?? 0),
        ];
        $chaptersCount = $statistics['published_chapters_count'];

        return array_merge([
            'id' => $novel->id,
            'title' => $novel->title,
            'slug' => $novel->slug,
            'author' => $novel->author?->name ?? __('ui.components.anonymous_author'),
            'genre' => $novel->categories?->pluck('name')->join(' · ') ?: __('ui.novel_detail.default_genre'),
            'desc' => $novel->synopsis ?? __('ui.messages.default_description'),
            'description' => $novel->synopsis,
            'chapters' => $chaptersCount,
            'chapters_count' => $chaptersCount,
            'status' => $novel->status === 'published' ? __('ui.messages.ongoing') : $novel->status,
            'cover_url' => $novel->cover_url,
            'cover_a' => '#355c5d',
            'cover_b' => '#d6aa67',
        ], $statistics);
    }

    private function novelStatistics(Novel $novel): array
    {
        $publishedChapters = $novel->relationLoaded('chapters')
            ? $novel->chapters->where('status', 'published')
            : $novel->publishedChapters()->get(['id', 'content', 'updated_at']);
        // Viewing a work updates its counter and may also touch updated_at;
        // published chapter timestamps are the content update signal.
        $lastUpdatedAt = null;

        foreach ($publishedChapters as $chapter) {
            foreach ([$chapter->published_at, $chapter->updated_at] as $timestamp) {
                if ($timestamp !== null && ($lastUpdatedAt === null || $timestamp->gt($lastUpdatedAt))) {
                    $lastUpdatedAt = $timestamp;
                }
            }
        }

        $lastUpdatedAt ??= $novel->published_at ?? $novel->created_at;

        return [
            'views_count' => (int) $novel->views_count,
            'published_chapters_count' => (int) ($novel->published_chapters_count ?? $publishedChapters->count()),
            'favorites_count' => (int) ($novel->favorites_count ?? $novel->favorites()->count()),
            'reviews_count' => (int) ($novel->reviews_count ?? $novel->activeRatings->count()),
            'word_count' => $publishedChapters->sum(fn (Chapter $chapter): int => $this->wordCount($chapter->content)),
            'last_updated_at' => $lastUpdatedAt,
        ];
    }

    private function wordCount(?string $content): int
    {
        $content = preg_replace('/\s+/u', '', strip_tags($content ?? '')) ?? '';

        return mb_strlen($content, 'UTF-8');
    }
}
