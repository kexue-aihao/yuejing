@extends('layouts.app')

@section('title', __('ui.dashboard.title'))
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">{{ __('ui.app.name') }}</p>
            <h1>{{ __('ui.dashboard.greeting', ['name' => auth()->user()->name ?? __('ui.account.overview')]) }}</h1>
            <p>{{ __('ui.dashboard.welcome') }}</p>
        </div>
        <a class="button button-primary" href="{{ route('novels.index') }}">{{ __('ui.dashboard.discover') }} <span>→</span></a>
    </div>

    <div class="dashboard-grid">
        <x-account-nav :active="$activeSection" />

        <div class="dashboard-content">
            @if ($activeSection === 'messages')
                @include('pages.messages._content', ['api' => $messagesApi, 'currentUserId' => $currentUserId, 'embedded' => true])
            @elseif ($activeSection === 'groups')
                @include('pages.groups._content', ['api' => $groupsApi, 'currentUserId' => $currentUserId, 'embedded' => true])
            @elseif ($activeSection === 'submissions')
                @include('pages.author._content', ['categories' => $categories, 'submissions' => $submissionHistory, 'embedded' => true])
            @else
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            <div class="metric-grid">
                <x-metric-card :label="__('ui.dashboard.reading_works')" :value="$readingCount" />
                <x-metric-card :label="__('ui.dashboard.favorite_works')" :value="$favoriteCount" />
                <x-metric-card :label="__('ui.dashboard.submitted_works')" :value="$submissionCounts->sum()" />
            </div>

            <section class="panel" id="history">
                <div class="panel-heading"><h2>{{ __('ui.dashboard.continue_reading') }}</h2><a href="{{ route('account.reading-records') }}">{{ __('ui.dashboard.view_all') }} →</a></div>
                @if ($reading->isNotEmpty())
                    <div class="data-list">
                        @foreach ($reading as $book)
                            <div class="data-list-row">
                                <span><strong>{{ $book['title'] }}</strong><br><small class="muted">{{ $book['author'] }} · {{ $book['status'] }}</small></span>
                                <span>{{ $book['progress'] }}</span>
                                @if ($book['slug'])
                                    <a class="text-link" href="{{ route('novels.read', ['novel' => $book['slug'], 'chapter' => $book['chapter']]) }}">{{ __('ui.dashboard.continue') }} <span>→</span></a>
                                @else
                                    <span class="muted">{{ __('ui.dashboard.unavailable') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty-state icon="📖" :message="__('ui.dashboard.no_reading')" compact>
                        <a class="button button-dark" href="{{ route('novels.index') }}">{{ __('ui.dashboard.browse_library') }}</a>
                    </x-empty-state>
                @endif
            </section>

            <section class="panel" id="favorites">
                <div class="panel-heading"><h2>{{ __('ui.dashboard.favorites') }}</h2><a href="{{ route('account.favorites') }}">{{ __('ui.dashboard.view_all') }} →</a></div>
                @if ($favorites->isNotEmpty())
                    <div class="account-book-list">
                        @foreach ($favorites as $favorite)
                            @if ($favorite->novel)
                                <a class="account-book-row" href="{{ route('novels.show', $favorite->novel->slug) }}">
                                    <span class="account-book-mark">阅</span>
                                    <span><strong>{{ $favorite->novel->title }}</strong><small>{{ $favorite->novel->author?->name ?? __('ui.components.anonymous_author') }}</small></span>
                                    <span class="text-link">{{ __('ui.dashboard.open') }} <span>→</span></span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <x-empty-state icon="♡" :message="__('ui.dashboard.no_favorites')" compact>
                        <a class="button button-dark" href="{{ route('novels.index') }}">{{ __('ui.dashboard.browse_library') }}</a>
                    </x-empty-state>
                @endif
            </section>

            <section class="panel" id="communication">
                <div class="panel-heading"><h2>{{ __('ui.dashboard.communication') }}</h2><span class="muted">{{ __('ui.dashboard.communication_subtitle') }}</span></div>
                <div class="communication-summary">
                    @if (Route::has('messages.page'))
                        <a class="communication-summary-card" href="{{ route('dashboard', ['section' => 'messages']) }}"><strong>{{ __('ui.account.messages') }}</strong><span>{{ __('ui.dashboard.start_message') }}</span><span class="text-link">{{ __('ui.dashboard.open_message') }} →</span></a>
                    @endif
                    @if (Route::has('groups.page'))
                        <a class="communication-summary-card" href="{{ route('dashboard', ['section' => 'groups']) }}"><strong>{{ __('ui.account.groups') }}</strong><span>{{ __('ui.dashboard.join_group') }}</span><span class="text-link">{{ __('ui.dashboard.open_group') }} →</span></a>
                    @endif
                </div>
            </section>

            @if (auth()->user()->isRole(['author', 'editor', 'admin']))
                <section class="panel" id="author-summary">
                    <div class="panel-heading"><h2>{{ __('ui.account.submissions') }}</h2><a href="{{ route('dashboard', ['section' => 'submissions']) }}">{{ __('ui.dashboard.submission_link') }} →</a></div>
                    <div class="submission-summary">
                        <div><span>{{ __('ui.dashboard.pending') }}</span><strong>{{ $submissionCounts->get('pending', 0) }}</strong></div>
                        <div><span>{{ __('ui.dashboard.approved') }}</span><strong>{{ $submissionCounts->get('approved', 0) }}</strong></div>
                        <div><span>{{ __('ui.dashboard.needs_changes') }}</span><strong>{{ $submissionCounts->get('rejected', 0) }}</strong></div>
                    </div>
                    @if ($submissions->isNotEmpty())
                        <div class="submission-mini-list">
                            @foreach ($submissions as $submission)
                                <div><span>{{ $submission->title }}</span><x-status-badge :status="$submission->status" /></div>
                            @endforeach
                        </div>
                    @else
                        <p class="muted dashboard-note">{{ __('ui.dashboard.no_submissions') }}</p>
                    @endif
                </section>
            @endif
            @endif
        </div>
    </div>
</main>
@endsection
