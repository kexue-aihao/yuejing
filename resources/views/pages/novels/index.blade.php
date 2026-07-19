@extends('layouts.app')

@php
    $novelSource = $novels ?? [];
    $paginator = is_object($novelSource) && method_exists($novelSource, 'hasPages') ? $novelSource : null;
    $novelItems = $paginator && method_exists($novelSource, 'getCollection')
        ? $novelSource->getCollection()
        : collect($novelSource);
    $query = trim((string) request('q', ''));
    $genre = trim((string) request('genre', ''));
    $sort = (string) request('sort', 'new');
    $sortLabels = ['new' => __('ui.library.latest'), 'hot' => __('ui.library.hot')];
    $sortLabel = $sortLabels[$sort] ?? $sortLabels['new'];
    $baseQuery = request()->except('page');

    $categorySource = $categories ?? [];
    $categoryItems = is_object($categorySource) && method_exists($categorySource, 'getCollection')
        ? $categorySource->getCollection()
        : collect($categorySource);
    $categoryItems = $categoryItems
        ->map(fn ($category) => [
            'name' => trim((string) data_get($category, 'name')),
            'slug' => trim((string) data_get($category, 'slug')) ?: trim((string) data_get($category, 'name')),
        ])
        ->filter(fn ($category) => $category['name'] !== '')
        ->unique('name')
        ->values();

    if ($categoryItems->isEmpty()) {
        $categoryItems = $novelItems
            ->flatMap(function ($book) {
                $genre = (string) data_get($book, 'genre', '');

                if (trim($genre) === '' || trim($genre) === __('ui.novel_detail.default_genre')) {
                    return [];
                }

                return preg_split('/\s*[^\p{L}\p{N}\s]+\s*/u', $genre) ?: [];
            })
            ->map(fn ($category) => ['name' => trim((string) $category), 'slug' => trim((string) $category)])
            ->filter(fn ($category) => $category['name'] !== '' && $category['name'] !== __('ui.novel_detail.default_genre'))
            ->unique('name')
            ->values();
    }

    $filterQuery = function (?string $value = null) use ($baseQuery): array {
        $query = $baseQuery;

        if ($value === null || $value === '') {
            unset($query['genre']);
        } else {
            $query['genre'] = $value;
        }

        return $query;
    };
    $selectedCategory = $categoryItems->first(fn ($category) => $genre !== '' && in_array($genre, [$category['slug'], $category['name']], true));
    $genreLabel = $selectedCategory['name'] ?? $genre;
    $hasFilters = $query !== '' || $genre !== '';
@endphp

@section('title', __('ui.library.title'))
@section('content')
<main>
    <section class="page-band library-page-header">
        <div class="site-shell">
            <p class="eyebrow">{{ __('ui.library.eyebrow') }}</p>
            <h1 class="library-page-heading">{{ __('ui.library.heading') }}</h1>
            <p class="page-intro">{{ __('ui.library.intro') }}</p>
        </div>
    </section>

    <section class="site-shell page-content library-page">
        <div class="library-context" aria-live="polite">
            <div class="library-context-main">
                <span class="library-context-label">{{ $query !== '' ? __('ui.nav.search') : __('ui.library.filters') }}</span>
                <strong>{{ $query !== '' ? $query : ($genreLabel !== '' ? $genreLabel : __('ui.library.all')) }}</strong>
            </div>
            <div class="library-context-sort">
                <span>{{ __('ui.library.sort_label') }}</span>
                <strong>{{ $sortLabel }}</strong>
                @if ($paginator)
                    <span class="library-context-summary">{{ __('ui.pagination.summary', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage(), 'total' => $paginator->total()]) }}</span>
                @endif
            </div>
        </div>

        <div class="library-toolbar">
            <nav class="filter-tabs" aria-label="{{ __('ui.library.filters') }}">
                <a class="{{ $genre === '' ? 'active' : '' }}" href="{{ Route::has('novels.index') ? route('novels.index', $filterQuery()) : '#' }}" @if ($genre === '') aria-current="page" @endif>{{ __('ui.library.all') }}</a>
                @foreach ($categoryItems as $category)
                    @php
                        $categoryName = $category['name'];
                        $categoryActive = in_array($genre, [$category['slug'], $categoryName], true);
                    @endphp
                    <a class="{{ $categoryActive ? 'active' : '' }}" href="{{ Route::has('novels.index') ? route('novels.index', $filterQuery($category['slug'])) : '#' }}" @if ($categoryActive) aria-current="page" @endif>{{ $categoryName }}</a>
                @endforeach
            </nav>

            <form method="get" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}" class="library-sort-form">
                @if ($query !== '')<input type="hidden" name="q" value="{{ $query }}">@endif
                @if ($genre !== '')<input type="hidden" name="genre" value="{{ $genre }}">@endif
                <label class="sr-only" for="sort">{{ __('ui.library.sort_label') }}</label>
                <select id="sort" name="sort" class="sort-select" onchange="this.form.submit()">
                    @foreach ($sortLabels as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($novelItems->isNotEmpty())
            <div class="library-grid">
                @foreach ($novelItems as $book)
                    @php
                        $bookData = is_array($book) ? $book : [
                            'title' => data_get($book, 'title', __('ui.library.untitled')),
                            'author' => data_get($book, 'author', __('ui.library.anonymous_author')),
                            'cover_a' => data_get($book, 'cover_a', '#355c5d'),
                            'cover_b' => data_get($book, 'cover_b', '#d6aa67'),
                        ];
                        $bookUrl = Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug')]) : '#';
                    @endphp
                    <article class="library-card">
                        <a class="library-card-cover" href="{{ $bookUrl }}" aria-label="{{ data_get($book, 'title', __('ui.library.untitled')) }}">
                            <x-book-cover :book="$bookData" size="small" />
                        </a>
                        <p class="library-card-genre">{{ data_get($book, 'genre', __('ui.novel_detail.default_genre')) }}</p>
                        <h2><a href="{{ $bookUrl }}">{{ data_get($book, 'title', __('ui.library.untitled')) }}</a></h2>
                        <div class="library-card-meta">
                            <span class="library-card-author">{{ data_get($book, 'author', __('ui.library.anonymous_author')) }}</span>
                            <span class="library-card-meta-line"><span>{{ __('ui.library.chapters', ['count' => data_get($book, 'chapters', 0)]) }}</span><span>{{ data_get($book, 'status', __('ui.library.ongoing')) }}</span></span>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($paginator)
                <div class="pagination-wrap library-pagination"><x-pagination :paginator="$paginator" /></div>
            @endif
        @else
            <div class="empty-state {{ $hasFilters ? 'is-filtered' : 'is-shelf-empty' }}">
                <span class="empty-icon" aria-hidden="true">{{ $hasFilters ? '?' : '+' }}</span>
                <h2>{{ __('ui.library.empty_heading') }}</h2>
                <p>{{ $hasFilters ? __('ui.admin.adjust_filters') : __('ui.library.empty_intro') }}</p>
                @if ($hasFilters)
                    <a class="button button-primary" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">{{ __('ui.library.all') }}</a>
                @else
                    <a class="button button-primary" href="{{ Route::has('home') ? route('home') : url('/') }}">{{ __('ui.library.back_home') }}</a>
                @endif
            </div>
        @endif
    </section>
</main>
@endsection
