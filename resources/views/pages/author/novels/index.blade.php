@extends('layouts.app')

@section('title', __('ui.author.novels_title'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">{{ __('ui.author.novels_eyebrow') }}</p>
            <h1>{{ __('ui.author.novels_heading') }}</h1>
            <p>{{ __('ui.author.novels_intro') }}</p>
        </div>
        @if (Route::has('author.submissions'))
            <a class="button button-primary" href="{{ route('dashboard', ['section' => 'submissions']) }}">{{ __('ui.author.new_submission') }} <span aria-hidden="true">→</span></a>
        @endif
    </div>

    <div class="dashboard-grid">
        <x-account-nav active="author-novels" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

            @forelse ($novels as $novel)
                @php
                    $novelStatus = $novel->status ?? 'draft';
                    $novelCover = ['title' => $novel->title, 'author' => auth()->user()->name, 'cover_url' => $novel->cover_url];
                    $categoryNames = $novel->categories?->pluck('name')->join(' · ');
                @endphp
                <article class="panel author-novel-card">
                    <div class="author-novel-card-main">
                        <div class="author-novel-cover"><x-book-cover :book="$novelCover" size="small" /></div>
                        <div class="author-novel-copy">
                            <div class="panel-heading">
                                <div><p class="panel-kicker">{{ $categoryNames ?: __('ui.author.uncategorized') }}</p><h2>{{ $novel->title }}</h2><p class="panel-description">{{ $novel->synopsis ?: __('ui.author.no_synopsis') }}</p></div>
                                <span class="status {{ in_array($novelStatus, ['draft', 'pending'], true) ? 'pending' : ($novelStatus === 'archived' ? 'rejected' : '') }}">{{ __('ui.status.'.$novelStatus) }}</span>
                            </div>
                            <div class="author-novel-meta"><span>{{ __('ui.author.chapter_count', ['count' => $novel->chapters_count ?? 0]) }}</span>@if ($novel->updated_at)<span>{{ __('ui.author.updated_at', ['date' => $novel->updated_at->format('Y-m-d')]) }}</span>@endif</div>
                            <div class="card-actions">
                                @if (Route::has('author.novels.edit'))<a class="button button-primary button-small" href="{{ route('author.novels.edit', $novel) }}">{{ __('ui.author.edit_work') }}</a>@endif
                                @if (Route::has('author.chapters.index'))<a class="button button-outline button-small" href="{{ route('author.chapters.index', $novel) }}">{{ __('ui.author.manage_chapters') }}</a>@endif
                                @if ($novel->slug && Route::has('novels.show'))<a class="button button-outline button-small" href="{{ route('novels.show', $novel->slug) }}">{{ __('ui.author.view_work') }}</a>@endif
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state"><h2>{{ __('ui.author.no_novels') }}</h2><p>{{ __('ui.author.no_novels_intro') }}</p>@if (Route::has('author.submissions'))<a class="button button-primary" href="{{ route('dashboard', ['section' => 'submissions']) }}">{{ __('ui.author.new_submission') }}</a>@endif</div>
            @endforelse

            @if (method_exists($novels, 'links'))<div class="pagination-wrap">{{ $novels->links() }}</div>@endif
        </div>
    </div>
</main>
@endsection
