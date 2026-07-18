@extends('layouts.app')

@section('title', __('ui.account_pages.history_title'))
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.account_pages.history_eyebrow') }}</p><h1>{{ __('ui.account.reading_records') }}</h1><p>{{ __('ui.account_pages.history_intro') }}</p></div><a class="button button-primary" href="{{ route('novels.index') }}">{{ __('ui.account_pages.find_story') }} <span>→</span></a></div>
    <div class="dashboard-grid">
        <x-account-nav active="reading-records" />
        <div class="dashboard-content">
            <section class="panel"><div class="panel-heading"><h2>{{ __('ui.account_pages.recent_reading') }}</h2><span class="muted">{{ __('ui.account_pages.total_records', ['count' => $records->total()]) }}</span></div>
                @if ($records->count() > 0)
                    <div class="data-list history-list">
                        @foreach ($records as $record)
                            <div class="data-list-row"><span><strong>{{ $record->novel?->title ?? __('ui.components.untitled_work') }}</strong><br><small class="muted">{{ $record->novel?->author?->name ?? __('ui.components.anonymous_author') }} · {{ __('ui.account_pages.read_to', ['percent' => (int) $record->progress]) }} · {{ $record->last_read_at?->format('Y-m-d H:i') ?? __('ui.account_pages.recent') }}</small></span><span>{{ __('ui.account_pages.chapter', ['number' => $record->chapter?->chapter_number ?? 1]) }}</span>@if ($record->novel)<a class="text-link" href="{{ route('novels.read', ['novel' => $record->novel->slug, 'chapter' => $record->chapter?->chapter_number ?? 1]) }}">{{ __('ui.account_pages.continue') }} <span>→</span></a>@else<span class="muted">{{ __('ui.account_pages.unavailable') }}</span>@endif</div>
                        @endforeach
                    </div>
                    <div class="pagination-wrap">{{ $records->links() }}</div>
                @else
                    <div class="empty-state"><h2>{{ __('ui.account_pages.no_history_heading') }}</h2><p>{{ __('ui.account_pages.no_history_intro') }}</p><a class="button button-dark" href="{{ route('novels.index') }}">{{ __('ui.account_pages.browse_library') }}</a></div>
                @endif
            </section>
        </div>
    </div>
</main>
@endsection
