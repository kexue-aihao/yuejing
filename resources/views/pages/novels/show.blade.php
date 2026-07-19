@extends('layouts.app')

@php
    $book = is_array($novel ?? null) ? $novel : [
        'title' => data_get($novel ?? null, 'title', __('ui.library.untitled')),
        'author' => data_get($novel ?? null, 'author', __('ui.novel_detail.anonymous_author')),
        'genre' => data_get($novel ?? null, 'genre', __('ui.novel_detail.default_genre')),
        'desc' => data_get($novel ?? null, 'description', ''),
        'status' => data_get($novel ?? null, 'status', __('ui.library.ongoing')),
        'slug' => data_get($novel ?? null, 'slug', ''),
    ];
    $isFavorited = $isFavorited ?? false;
    $ratings = $ratings ?? collect();
    $averageRating = $averageRating ?? data_get($statistics ?? [], 'average_rating');
    $averageRatingLevel = $averageRatingLevel ?? data_get($statistics ?? [], 'average_rating_level');
    $currentRating = $currentRating ?? null;
    $novelModel = $novelModel ?? null;
    $statistics = is_array($statistics ?? null) ? $statistics : [];
    $chapters = isset($chapters) && is_iterable($chapters) ? collect($chapters) : collect();
    $chapterCount = (int) data_get($statistics, 'published_chapters_count', $chapters->count());
    $reviewCount = (int) data_get($statistics, 'rating_count', data_get($statistics, 'reviews_count', $ratings->count()));
    $lastUpdatedAt = data_get($statistics, 'last_updated_at');
    $lastUpdatedText = $lastUpdatedAt
        ? ($lastUpdatedAt instanceof \DateTimeInterface ? $lastUpdatedAt->format('Y-m-d H:i') : \Illuminate\Support\Carbon::parse($lastUpdatedAt)->format('Y-m-d H:i'))
        : __('reviews.no_update');
    $reviewApiUrl = filled($book['slug'] ?? null)
        ? url('/api/novels/'.rawurlencode((string) $book['slug']).'/reviews')
        : '';
    $readUrl = Route::has('novels.read') && filled($book['slug'] ?? null)
        ? route('novels.read', ['novel' => $book['slug'], 'chapter' => 1])
        : null;
@endphp

@section('title', $book['title'].' · '.__('ui.novel_detail.title_suffix'))
@section('content')
<main>
    <section class="detail-band">
        <div class="site-shell detail-grid">
            <div class="detail-cover"><x-book-cover :book="$book" size="large" /></div>
            <div class="detail-copy">
                <p class="eyebrow">{{ $book['genre'] ?? __('ui.novel_detail.default_genre') }}</p>
                <h1>{{ $book['title'] }}</h1>
                <p class="detail-author">{{ $book['author'] ?? __('ui.novel_detail.anonymous_author') }} {{ __('ui.novel_detail.author_suffix') }}</p>
                @if (filled($book['desc'] ?? null))
                    <p class="detail-desc">{{ $book['desc'] }}</p>
                @endif
                <div class="detail-actions">
                    <a class="button button-primary" href="{{ $readUrl ?? '#' }}">{{ __('ui.novel_detail.start_reading') }} <span aria-hidden="true">↗</span></a>
                    <a class="button button-outline" href="#reviews">{{ __('reviews.read_cta') }}</a>
                    @auth
                        <form method="POST" action="{{ $isFavorited ? route('novels.unfavorite', $book['slug']) : route('novels.favorite', $book['slug']) }}">
                            @csrf
                            @if ($isFavorited) @method('DELETE') @endif
                            <button class="button button-outline" type="submit">{{ $isFavorited ? __('ui.novel_detail.favorited') : __('ui.novel_detail.favorite') }}</button>
                        </form>
                    @else
                        <a class="button button-outline" href="{{ route('login.page') }}">{{ __('ui.novel_detail.favorite') }}</a>
                    @endauth
                </div>
                <div class="detail-stats" aria-label="{{ __('reviews.statistics') }}">
                    <span><strong data-review-stat="published_chapters_count">{{ number_format($chapterCount) }}</strong>{{ __('reviews.chapters') }}</span>
                    <span><strong>{{ $book['status'] ?? __('ui.library.ongoing') }}</strong>{{ __('ui.novel_detail.status') }}</span>
                    <span><strong data-review-average>{{ $averageRating !== null ? number_format((float) $averageRating, 1) : __('reviews.no_rating') }}</strong>{{ __('reviews.average_rating') }}</span>
                    <span><strong data-review-stat="views_count">{{ number_format((int) data_get($statistics, 'views_count', 0)) }}</strong>{{ __('reviews.views') }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="site-shell page-content">
        <div class="chapter-layout">
            <div>
                <div class="section-heading">
                    <div><p class="eyebrow">{{ __('ui.novel_detail.chapter_eyebrow') }}</p><h2>{{ __('ui.novel_detail.chapter_list') }}</h2></div>
                    <span class="muted" data-review-chapter-total>{{ __('reviews.chapter_total', ['count' => $chapterCount]) }}</span>
                </div>
                <div class="chapter-list">
                    @forelse ($chapters as $chapter)
                        <a class="chapter-item" href="{{ Route::has('novels.read') && filled($book['slug'] ?? null) ? route('novels.read', ['novel' => $book['slug'], 'chapter' => data_get($chapter, 'number', $loop->iteration)]) : '#' }}">
                            <span>{{ __('ui.novel_detail.chapter_prefix', ['number' => data_get($chapter, 'number', $loop->iteration)]) }}　{{ data_get($chapter, 'title', __('ui.novel_detail.unnamed_chapter')) }}</span>
                            <small>{{ data_get($chapter, 'date', __('ui.novel_detail.pending_update')) }}</small>
                        </a>
                    @empty
                        <p class="empty-state compact">{{ __('reviews.no_chapters') }}</p>
                    @endforelse
                </div>
            </div>
            <aside class="detail-aside">
                <h3>{{ __('ui.novel_detail.info') }}</h3>
                <div class="aside-row"><span>{{ __('ui.novel_detail.type') }}</span><strong>{{ $book['genre'] ?? __('ui.library.untitled') }}</strong></div>
                <div class="aside-row"><span>{{ __('ui.novel_detail.last_updated') }}</span><strong data-review-stat="last_updated_at">{{ $lastUpdatedText }}</strong></div>
                <div class="aside-row"><span>{{ __('ui.novel_detail.word_count') }}</span><strong data-review-stat="word_count">{{ number_format((int) data_get($statistics, 'word_count', 0)) }}</strong></div>
                <div class="aside-row"><span>{{ __('reviews.favorites') }}</span><strong data-review-stat="favorites_count">{{ number_format((int) data_get($statistics, 'favorites_count', 0)) }}</strong></div>
            </aside>
        </div>
    </section>

    <section id="reviews" class="site-shell page-content review-section" data-reviews-app data-reviews-url="{{ $reviewApiUrl }}">
        <div class="section-heading">
            <div><p class="eyebrow">{{ __('reviews.eyebrow') }}</p><h2>{{ __('reviews.title') }}</h2></div>
            <div class="review-summary" aria-live="polite">
                <span class="muted" data-review-summary>{{ $averageRating !== null ? number_format((float) $averageRating, 1).' / 9.9 · '.($averageRatingLevel ? __('reviews.level_'.$averageRatingLevel) : '') : __('reviews.no_rating') }}</span>
                <span class="muted" data-review-rating-count>{{ __('reviews.rating_count', ['count' => $reviewCount]) }}</span>
            </div>
        </div>
        <p class="review-status muted" data-reviews-status aria-live="polite"></p>

        @auth
            <div class="panel review-form-panel">
                <form method="POST" action="{{ route('novels.rate', $novelModel ?? $book['slug']) }}" data-review-form>
                    @csrf
                    <div class="review-form-grid">
                        <div class="form-field"><label for="rating">{{ __('reviews.rating_label') }}</label><input id="rating" name="rating" type="number" min="1" max="9.9" step="0.1" value="{{ old('rating', $currentRating?->rating) }}" required></div>
                        <div class="form-field"><label for="review">{{ __('reviews.review_label') }}</label><textarea id="review" name="review" maxlength="2000" placeholder="{{ __('reviews.review_placeholder') }}">{{ old('review', $currentRating?->review) }}</textarea></div>
                    </div>
                    <fieldset class="review-criteria"><legend>{{ __('reviews.criteria_label') }}</legend><div class="review-criteria-grid">
                        @foreach (['plot', 'writing', 'characters', 'originality'] as $criterion)
                            <label class="form-field" for="criteria_{{ $criterion }}"><span>{{ __('reviews.'.$criterion) }}</span><input id="criteria_{{ $criterion }}" name="criteria[{{ $criterion }}]" type="number" min="1" max="10" value="{{ old('criteria.'.$criterion, data_get($currentRating?->criteria, $criterion)) }}"></label>
                        @endforeach
                    </div></fieldset>
                    <div class="review-actions"><button class="button button-primary" type="submit">{{ __('reviews.submit') }}</button>@if ($currentRating)<span class="muted">{{ __('reviews.withdraw_hint') }}</span>@endif</div>
                </form>
                <form method="POST" action="{{ route('novels.rating.withdraw', $novelModel ?? $book['slug']) }}" class="review-withdraw-form" data-review-withdraw-form @unless ($currentRating) hidden @endunless>@csrf @method('DELETE')<button class="button button-outline" type="submit">{{ __('reviews.withdraw') }}</button></form>
            </div>
        @else
            <p class="muted"><a class="text-link" href="{{ route('login.page') }}">{{ __('ui.nav.login') }}</a> {{ __('reviews.login_to_review') }}</p>
        @endauth

        <div class="review-list" data-review-list aria-live="polite">
            @forelse ($ratings as $rating)
                <article class="panel review-item"><div class="review-item-head"><strong>{{ $rating['user'] ?? __('reviews.anonymous_user') }}</strong><span>{{ number_format((float) $rating['rating'], 1) }} · {{ __('reviews.level_'.$rating['level']) }}</span></div>@if (!empty($rating['review']))<p>{{ $rating['review'] }}</p>@endif @if (!empty($rating['criteria']))<dl class="review-criteria-summary">@foreach ($rating['criteria'] as $key => $value)<div><dt>{{ __('reviews.'.$key) }}</dt><dd>{{ $value }}/10</dd></div>@endforeach</dl>@endif</article>
            @empty
                <p class="muted">{{ __('reviews.no_rating') }}</p>
            @endforelse
        </div>
    </section>
</main>
@endsection
