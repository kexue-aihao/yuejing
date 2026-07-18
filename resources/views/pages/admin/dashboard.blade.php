@extends('layouts.app')

@section('title', __('ui.admin.console').' · '.__('ui.admin.title_suffix'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div><p class="eyebrow">{{ __('ui.admin.console_eyebrow') }}</p><h1>{{ __('ui.admin.console') }}</h1><p>{{ __('ui.admin.console_intro') }}</p></div>
        <span class="status">{{ __('ui.admin.system_ok') }}</span>
    </div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'dashboard'])
        <div class="dashboard-content">
            @if (session('status'))<x-alert type="success" :message="session('status')" dismissible />@endif
            <div class="metric-grid">
                <x-metric-card :label="__('ui.admin.registered_users')" :value="$users" />
                <x-metric-card :label="__('ui.admin.novel_works')" :value="$novels" />
                <x-metric-card :label="__('ui.admin.chapter_total')" :value="$chapters" />
                <x-metric-card :label="__('ui.admin.pending_submissions')" :value="$pending_submissions" />
            </div>
            <section class="panel">
                <div class="panel-heading"><h2>{{ __('ui.admin.recent_submissions') }}</h2><a href="{{ route('admin.submissions.index') }}">{{ __('ui.admin.view_all') }} →</a></div>
                <div class="data-list">
                    @forelse ($recent_submissions as $submission)
                        <div class="data-list-row"><span><strong>{{ $submission->title }}</strong><small class="row-subtitle">{{ $submission->user?->name ?? __('ui.admin.anonymous_user') }} · {{ $submission->created_at?->format('Y-m-d H:i') }}</small></span><x-status-badge :status="$submission->status" /><a class="text-button" href="{{ route('admin.submissions.index') }}">{{ __('ui.admin.process') }}</a></div>
                    @empty
                        <x-empty-state icon="📋" :message="__('ui.admin.no_submissions')" compact />
                    @endforelse
                </div>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>{{ __('ui.admin.recent_works') }}</h2><a href="{{ route('admin.novels.index') }}">{{ __('ui.admin.manage_novels') }} →</a></div>
                <div class="data-list">
                    @forelse ($recent_novels as $novel)
                        <div class="data-list-row"><span><strong>{{ $novel->title }}</strong><small class="row-subtitle">{{ $novel->author?->name ?? __('ui.admin.anonymous_author') }} · {{ $novel->created_at?->format('Y-m-d') }}</small></span><x-status-badge :status="$novel->status" /><a class="text-button" href="{{ route('admin.novels.index') }}">{{ __('ui.admin.edit') }}</a></div>
                    @empty
                        <x-empty-state icon="📚" :message="__('ui.admin.no_novels')" compact />
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
