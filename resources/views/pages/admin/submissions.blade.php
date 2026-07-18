@extends('layouts.app')

@section('title', __('ui.admin.review_submissions').' · '.__('ui.admin.title_suffix'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.admin.review_eyebrow') }}</p><h1>{{ __('ui.admin.review_submissions') }}</h1><p>{{ __('ui.admin.review_intro') }}</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'submissions'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <div class="filter-tabs admin-filters"><a class="{{ !request('status') ? 'active' : '' }}" href="{{ route('admin.submissions.index') }}">{{ __('ui.admin.all') }}</a><a class="{{ request('status') === 'pending' ? 'active' : '' }}" href="{{ route('admin.submissions.index', ['status' => 'pending']) }}">{{ __('ui.admin.pending') }}</a><a class="{{ request('status') === 'approved' ? 'active' : '' }}" href="{{ route('admin.submissions.index', ['status' => 'approved']) }}">{{ __('ui.admin.approved_filter') }}</a><a class="{{ request('status') === 'rejected' ? 'active' : '' }}" href="{{ route('admin.submissions.index', ['status' => 'rejected']) }}">{{ __('ui.admin.rejected') }}</a></div>
            @forelse ($submissions as $submission)
                <article class="panel submission-review-card">
                    <div class="panel-heading"><div><h2>{{ $submission->title }}</h2><p class="panel-description">{{ $submission->user?->name ?? __('ui.admin.anonymous_user') }} · {{ $submission->user?->email ?? __('ui.auth.email') }} · {{ __('ui.admin.submitted_at') }} {{ $submission->created_at?->format('Y-m-d H:i') }}</p></div><span class="status {{ $submission->status === 'pending' ? 'pending' : ($submission->status === 'rejected' ? 'rejected' : '') }}">{{ ['pending' => __('ui.admin.pending'), 'approved' => __('ui.admin.approved_filter'), 'rejected' => __('ui.admin.rejected')][$submission->status] ?? __('ui.components.no_content') }}</span></div>
                    @if ($submission->synopsis)<p class="submission-synopsis">{{ $submission->synopsis }}</p>@endif
                    <details class="manuscript-details"><summary>{{ __('ui.admin.view_first_chapter') }}</summary><div class="manuscript-preview markdown-preview">{!! $submission->manuscript_html !!}</div><details class="manuscript-source"><summary>{{ __('ui.admin.view_markdown') }}</summary><pre>{{ $submission->manuscript }}</pre></details></details>
                    @if ($submission->status === 'pending')
                        <form class="review-form" method="POST" action="{{ route('admin.submissions.review', $submission) }}">@csrf @method('PUT')<div class="form-field"><label for="review_note_{{ $submission->id }}">{{ __('ui.admin.review_note') }}</label><textarea id="review_note_{{ $submission->id }}" name="review_note" rows="3" placeholder="{{ __('ui.admin.review_placeholder') }}"></textarea></div><div class="review-actions"><button class="button button-primary" name="status" value="approved" type="submit">{{ __('ui.admin.approve_publish') }}</button><button class="button button-outline" name="status" value="rejected" type="submit">{{ __('ui.admin.reject_submission') }}</button></div></form>
                    @elseif ($submission->review_note)
                        <p class="review-note"><strong>{{ __('ui.admin.review_note_prefix') }}</strong>{{ $submission->review_note }}</p>
                    @endif
                </article>
            @empty
                <div class="empty-state"><h2>{{ __('ui.admin.no_matching_submissions') }}</h2><p>{{ __('ui.admin.adjust_filters') }}</p></div>
            @endforelse
            <div class="pagination-wrap">{{ $submissions->links() }}</div>
        </div>
    </div>
</main>
@endsection
