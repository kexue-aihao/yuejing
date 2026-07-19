@extends('layouts.app')

@php
    $novelItems = collect($novels ?? []);
@endphp

@section('title', __('ui.library.title'))
@section('content')
<main>
    <section class="site-shell page-content">
        <div class="library-toolbar"><nav class="filter-tabs" aria-label="{{ __('ui.library.filters') }}"><a class="{{ request('genre') ? '' : 'active' }}" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">{{ __('ui.library.all') }}</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '都市']) : '#' }}">{{ __('ui.library.urban') }}</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '古言']) : '#' }}">{{ __('ui.library.ancient') }}</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '悬疑']) : '#' }}">{{ __('ui.library.mystery') }}</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '科幻']) : '#' }}">{{ __('ui.library.scifi') }}</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '青春']) : '#' }}">{{ __('ui.library.youth') }}</a></nav><form method="get" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}"><label class="sr-only" for="sort">{{ __('ui.library.sort_label') }}</label><select id="sort" name="sort" class="sort-select" onchange="this.form.submit()"><option value="new" @selected(request('sort', 'new') === 'new')>{{ __('ui.library.latest') }}</option><option value="hot" @selected(request('sort') === 'hot')>{{ __('ui.library.hot') }}</option><option value="finished" @selected(request('sort') === 'finished')>{{ __('ui.library.finished') }}</option></select></form></div>
        @if (count($novelItems))<div class="library-grid">@foreach ($novelItems as $book)<article class="library-card"><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug')]) : '#' }}"><x-book-cover :book="is_array($book) ? $book : ['title' => data_get($book, 'title', __('ui.library.untitled')), 'author' => data_get($book, 'author', __('ui.library.anonymous_author')), 'cover_a' => data_get($book, 'cover_a', '#355c5d'), 'cover_b' => data_get($book, 'cover_b', '#d6aa67')]" size="small" /></a><h3><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug')]) : '#' }}">{{ data_get($book, 'title', __('ui.library.untitled')) }}</a></h3><div class="library-card-meta"><span>{{ data_get($book, 'author', __('ui.library.anonymous_author')) }}</span><span>{{ __('ui.library.chapters', ['count' => data_get($book, 'chapters', 0)]) }} · {{ data_get($book, 'status', __('ui.library.ongoing')) }}</span></div></article>@endforeach</div>@else<div class="empty-state"><h2>{{ __('ui.library.empty_heading') }}</h2><p>{{ __('ui.library.empty_intro') }}</p><a class="button button-primary" href="{{ Route::has('home') ? route('home') : url('/') }}">{{ __('ui.library.back_home') }}</a></div>@endif
    </section>
</main>
@endsection
