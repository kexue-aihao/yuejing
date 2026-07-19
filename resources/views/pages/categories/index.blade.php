@extends('layouts.app')

@section('title', __('ui.categories.title'))

@section('content')
<main>
    <section class="page-band info-page-header"><div class="site-shell"><p class="eyebrow">{{ __('ui.categories.eyebrow') }}</p><h1>{{ __('ui.categories.heading') }}</h1><p class="page-intro">{{ __('ui.categories.intro') }}</p></div></section>
    <section class="site-shell page-content category-directory">
        @forelse ($categories as $category)
            <a class="category-directory-item" href="{{ route('novels.index', ['genre' => $category->slug]) }}"><span><strong>{{ $category->name }}</strong><small>{{ $category->description ?: __('ui.categories.item_intro') }}</small></span><span class="category-directory-count">{{ __('ui.categories.work_count', ['count' => $category->novels_count]) }} <span aria-hidden="true">-&gt;</span></span></a>
        @empty
            <div class="empty-state"><h2>{{ __('ui.categories.empty_heading') }}</h2><p>{{ __('ui.categories.empty_intro') }}</p><a class="button button-primary" href="{{ route('novels.index') }}">{{ __('ui.nav.all_books') }}</a></div>
        @endforelse
    </section>
</main>
@endsection
