@extends('layouts.app')

@section('title', __('ui.account_pages.favorites_title'))
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.account_pages.favorites_eyebrow') }}</p><h1>{{ __('ui.account.favorites') }}</h1><p>{{ __('ui.account_pages.favorites_intro') }}</p></div><a class="button button-primary" href="{{ route('novels.index') }}">{{ __('ui.account_pages.find_story') }} <span>→</span></a></div>
    <div class="dashboard-grid">
        <x-account-nav active="favorites" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><h2>{{ __('ui.account_pages.favorite_works') }}</h2><span class="muted">{{ __('ui.account_pages.total_works', ['count' => $favorites->total()]) }}</span></div>
                @if ($favorites->count() > 0)
                    <div class="account-book-list account-book-list-large">
                        @foreach ($favorites as $favorite)
                            @if ($favorite->novel)
                                <div class="account-book-row"><a class="account-book-main" href="{{ route('novels.show', $favorite->novel->slug) }}"><span class="account-book-mark">阅</span><span><strong>{{ $favorite->novel->title }}</strong><small>{{ $favorite->novel->author?->name ?? __('ui.components.anonymous_author') }} · {{ __('ui.account_pages.favorited_at') }} {{ $favorite->created_at?->format('Y-m-d') }}</small></span></a><form method="POST" action="{{ route('novels.unfavorite', $favorite->novel) }}">@csrf @method('DELETE')<button class="text-button" type="submit">{{ __('ui.account_pages.remove_favorite') }}</button></form></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="pagination-wrap">{{ $favorites->links() }}</div>
                @else
                    <div class="empty-state"><h2>{{ __('ui.account_pages.no_favorites_heading') }}</h2><p>{{ __('ui.account_pages.no_favorites_intro') }}</p><a class="button button-dark" href="{{ route('novels.index') }}">{{ __('ui.account_pages.browse_library') }}</a></div>
                @endif
            </section>
        </div>
    </div>
</main>
@endsection
