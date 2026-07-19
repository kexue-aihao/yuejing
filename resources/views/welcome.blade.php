@extends('layouts.app')

@php
    $featured = collect($featured ?? []);
    $newBooks = collect($newBooks ?? []);
    $ranking = collect($ranking ?? []);
    $categories = collect($categories ?? []);
    $homeStats = $homeStats ?? ['works_count' => 0, 'readers_count' => 0];
    $heroMain = $featured->first();
    $heroBack = $featured->skip(1)->first();
@endphp

@section('title', __('ui.home.title'))
@section('content')
<main>
    <section class="hero-band"><div class="site-shell hero-grid"><div class="hero-copy"><p class="eyebrow"><span class="eyebrow-mark"></span> {{ __('ui.home.eyebrow') }}</p><h1>{{ __('ui.home.headline_before') }}<br><em>{{ __('ui.home.headline_after') }}</em></h1><p class="hero-lead">{{ __('ui.home.lead') }}</p><div class="hero-actions"><a class="button button-primary" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">{{ __('ui.home.start_reading') }} <span aria-hidden="true">↗</span></a><a class="text-link" href="#featured">{{ __('ui.home.weekly_picks') }} <span aria-hidden="true">→</span></a></div><div class="hero-meta" aria-label="{{ __('ui.home.stats_label') }}"><span><strong>{{ number_format((int) data_get($homeStats, 'works_count', 0)) }} {{ __('ui.home.works_count') }}</strong></span><span><strong>{{ number_format((int) data_get($homeStats, 'readers_count', 0)) }} {{ __('ui.home.readers_count') }}</strong></span><span><strong>{{ __('ui.home.daily_update') }}</strong></span></div></div><div class="hero-stage" aria-label="{{ __('ui.home.novel_preview') }}"><div class="stage-note stage-note-top">{{ __('ui.home.editors_note') }} <span>01</span></div><div class="stage-book stage-book-back">@if($heroBack)<span>{{ $heroBack['title'] }}</span>@endif</div><div class="stage-book stage-book-main">@if($heroMain)<span>{{ $heroMain['title'] }}</span><small>{{ __('ui.components.author_credit', ['author' => $heroMain['author']]) }}</small>@endif</div><div class="stage-note stage-note-bottom"><span>{{ __('ui.home.editor_pick') }}</span> {{ __('ui.home.read_slowly') }}</div><div class="stage-line stage-line-one"></div><div class="stage-line stage-line-two"></div></div></div></section>

    <section id="featured" class="site-shell section-block"><div class="section-heading"><div><p class="eyebrow">{{ __('ui.home.featured_eyebrow') }}</p><h2>{{ __('ui.home.featured_title') }}</h2></div><a class="text-link" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">{{ __('ui.home.all_featured') }} <span>→</span></a></div><div class="featured-grid">@forelse ($featured as $index => $book)<article class="featured-card {{ $index === 0 ? 'featured-card-primary' : '' }}"><a href="{{ route('novels.show', ['novel' => $book['slug']]) }}" class="cover-link"><x-book-cover :book="$book" size="large" /></a><div class="featured-card-copy"><p class="book-kicker">{{ $book['genre'] }}</p><h3><a href="{{ route('novels.show', ['novel' => $book['slug']]) }}">{{ $book['title'] }}</a></h3><p>{{ $book['desc'] ?? __('ui.novel_detail.default_description') }}</p><div class="book-byline"><span>{{ $book['author'] }}</span><span>{{ __('ui.home.updated_to', ['count' => $book['latest_chapter_number'] ?? $book['chapters']]) }}</span></div></div></article>@empty<p class="muted">{{ __('ui.library.empty_intro') }}</p>@endforelse</div></section>

    <section class="site-shell section-block section-divider"><div class="section-heading"><div><p class="eyebrow">{{ __('ui.home.new_eyebrow') }}</p><h2>{{ __('ui.home.new_title') }}</h2></div><a class="text-link" href="{{ Route::has('novels.index') ? route('novels.index', ['sort' => 'new']) : '#' }}">{{ __('ui.home.discover_more') }} <span>→</span></a></div><div class="new-books-grid">@forelse ($newBooks as $book)<article class="book-row"><a href="{{ route('novels.show', ['novel' => $book['slug']]) }}"><x-book-cover :book="$book" size="small" /></a><div class="book-row-copy"><p class="book-kicker">{{ $book['genre'] }}</p><h3><a href="{{ route('novels.show', ['novel' => $book['slug']]) }}">{{ $book['title'] }}</a></h3><p class="muted">{{ $book['author'] }}</p><span class="chapter-count">{{ __('ui.home.updated_to', ['count' => $book['chapters']]) }}</span></div></article>@empty<p class="muted">{{ __('ui.library.empty_intro') }}</p>@endforelse</div></section>

    <section class="rank-band"><div class="site-shell ranking-layout"><div class="rank-intro"><p class="eyebrow">{{ __('ui.home.ranking_eyebrow') }}</p><h2>{{ __('ui.home.ranking_title') }}<br><em>{{ __('ui.home.ranking_question') }}</em></h2><p>{{ __('ui.home.ranking_intro') }}</p><a class="button button-dark" href="{{ Route::has('novels.index') ? route('novels.index', ['sort' => 'hot']) : '#' }}">{{ __('ui.home.full_ranking') }} <span>→</span></a></div><div class="ranking-list">@forelse ($ranking as $index => $book)<a class="ranking-item" href="{{ route('novels.show', ['novel' => $book['slug']]) }}"><span class="rank-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><span class="rank-title"><strong>{{ $book['title'] }}</strong><small>{{ $book['author'] }} · {{ $book['tag'] ?? $book['genre'] }}</small></span><span class="rank-score">{{ $book['score'] ?? __('reviews.no_rating') }}<small>{{ __('ui.home.score') }}</small></span></a>@empty<p class="muted">{{ __('ui.library.empty_intro') }}</p>@endforelse</div></div></section>

    <section id="categories" class="site-shell category-strip"><p class="eyebrow">{{ __('ui.home.category_eyebrow') }}</p><div class="category-links">@forelse ($categories as $category)<a href="{{ route('novels.index', ['genre' => $category->name]) }}">{{ $category->name }} <span>{{ number_format((int) $category->novels_count) }}</span></a>@empty<p class="muted">{{ __('ui.library.empty_intro') }}</p>@endforelse</div></section>

    @php($recommendationItems = collect($recommendations ?? []))
    <section class="site-shell section-block recommendation-section" data-recommendations-app data-api-url="{{ $recommendationApiUrl ?? url('/api/recommendations') }}" data-novel-base="{{ url('/novels') }}">
        <div class="section-heading"><div><p class="eyebrow">{{ __('ui.home.ranking_eyebrow') }}</p><h2>{{ __('reviews.recommendations_title') }}</h2><p class="panel-subtitle">{{ __('reviews.recommendations_intro') }}</p></div><span class="recommendation-status muted" data-recommendation-status aria-live="polite"></span></div>
        <div class="recommendation-grid" data-recommendation-list>
            @forelse ($recommendationItems as $recommendation)
                <a class="recommendation-item" data-recommendation-item href="{{ route('novels.show', ['novel' => $recommendation->slug]) }}"><span class="recommendation-mark" aria-hidden="true">Y</span><span><strong>{{ $recommendation->title }}</strong><small>{{ $recommendation->author?->name ?? __('ui.components.anonymous_author') }} · {{ $recommendation->categories->pluck('name')->join(' · ') }}</small></span><span aria-hidden="true">→</span></a>
            @empty
                <p class="muted" data-recommendation-empty>{{ __('reviews.recommendations_empty') }}</p>
            @endforelse
        </div>
    </section>
</main>
@endsection
