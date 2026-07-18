@extends('layouts.app')
@section('title', __('ui.admin.audit_logs').' · '.__('ui.admin.title_suffix'))
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">{{ __('ui.admin.audit_eyebrow') }}</p>
            <h1>{{ __('ui.admin.audit_logs') }}</h1>
            <p>{{ __('ui.admin.audit_intro') }}</p>
        </div>
    </div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'audit-logs'])
        <div class="dashboard-content">
            <section class="panel submission-audit-panel">
                <div class="submission-audit-list">
                    @forelse ($logs as $log)
                        @php
                            $submission = $log->auditable;
                            $metadata = $log->metadata ?? [];
                            $actionLabels = [
                                'submission.created' => __('ui.admin.review_submissions'),
                                'submission.approved' => __('ui.admin.approve_publish'),
                                'submission.rejected' => __('ui.admin.reject_submission'),
                            ];
                        @endphp
                        <article class="submission-audit-row">
                            <div class="submission-audit-main">
                                <div class="submission-audit-heading">
                                    <strong>{{ $actionLabels[$log->action] ?? __('ui.components.no_content') }}</strong>
                                    <span class="submission-audit-id">{{ __('ui.admin.review_submissions') }} #{{ $log->auditable_id }}</span>
                                </div>
                                <h2>{{ $submission?->title ?? ($metadata['title'] ?? __('ui.admin.no_matching_submissions')) }}</h2>
                                <p class="submission-audit-meta">
                                    {{ __('ui.admin.anonymous_author') }}: {{ $submission?->user?->name ?? __('ui.admin.anonymous_author') }}
                                    @if($submission?->category?->name) · {{ __('ui.admin.category_name') }}: {{ $submission->category->name }} @endif
                                    · {{ __('ui.admin.audit_operator') }}: {{ $log->user?->name ?? __('ui.admin.console') }}
                                </p>
                                @if(!empty($metadata['review_note']))
                                    <p class="submission-audit-note">{{ __('ui.admin.review_note_prefix') }}{{ $metadata['review_note'] }}</p>
                                @endif
                            </div>
                            <div class="submission-audit-side">
                                <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('Y-m-d H:i:s') }}</time>
                                <span>{{ __('ui.components.current_ip') }}: {{ $log->ip_address ?? __('ui.components.no_content') }}</span>
                            </div>
                        </article>
                    @empty
                        <x-empty-state icon="📋" :message="__('ui.admin.no_audit_logs')" compact />
                    @endforelse
                </div>
                <div class="pagination-wrap">{{ $logs->links() }}</div>
            </section>
        </div>
    </div>
</main>
@endsection
