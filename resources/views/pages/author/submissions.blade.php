@extends('layouts.app')

@section('title', __('ui.author.title'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">{{ __('ui.author.eyebrow') }}</p>
            <h1>{{ __('ui.author.heading') }}</h1>
            <p>{{ __('ui.author.intro') }}</p>
        </div>
    </div>

    <div class="author-layout">
        <section class="panel">
            <div class="panel-heading">
                <div><p class="panel-kicker">{{ __('ui.author.editor_eyebrow') }}</p><h2>{{ __('ui.author.new_submission') }}</h2></div>
                <span class="muted">{{ __('ui.author.draft_saved') }}</span>
            </div>
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif

            <form class="form-stack" method="POST" action="{{ route('author.submissions.store') }}" enctype="multipart/form-data" data-markdown-editor>
                @csrf
                <input type="hidden" name="manuscript_format" value="markdown">
                <div class="form-field"><label for="title">{{ __('ui.author.title_label') }}</label><input id="title" name="title" value="{{ old('title') }}" placeholder="{{ __('ui.author.title_placeholder') }}" required></div>
                <div class="form-field"><label for="category_id">{{ __('ui.author.genre_label') }}</label><select id="category_id" name="category_id">@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                <div class="form-field"><label for="cover">{{ __('ui.author.cover_label') }}</label><input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" required><p class="form-help">{{ __('ui.author.cover_help') }}</p></div>
                <div class="form-field"><label for="summary">{{ __('ui.author.summary_label') }}</label><textarea id="summary" name="summary" placeholder="{{ __('ui.author.summary_placeholder') }}" required>{{ old('summary') }}</textarea></div>
                <div class="form-field manuscript-editor-field">
                    <div class="editor-label-row"><label for="content">{{ __('ui.author.first_chapter') }}</label><button class="text-button" type="button" data-clear-markdown-draft>{{ __('ui.author.clear_draft') }}</button></div>
                    <textarea id="content" name="content" data-markdown-source placeholder="{{ __('ui.author.content_placeholder') }}">{{ old('content') }}</textarea>
                    <div data-vditor-editor hidden aria-label="{{ __('ui.author.editor_label') }}"></div>
                    <p class="form-help">{{ __('ui.author.editor_help') }}</p>
                </div>
                <button class="button button-primary" type="submit">{{ __('ui.author.submit_review') }} <span>→</span></button>
            </form>
        </section>

        <aside class="author-note">
            <p class="eyebrow">{{ __('ui.author.notes_eyebrow') }}</p>
            <h2>{{ __('ui.author.notes_title') }}</h2>
            <p>{{ __('ui.author.notes_intro') }}</p>
            <ul><li>{{ __('ui.author.rule_frequency') }}</li><li>{{ __('ui.author.rule_original') }}</li><li>{{ __('ui.author.rule_review') }}</li></ul>
            <p class="no-script-note">{{ __('ui.author.no_script') }}</p>
        </aside>
    </div>

    @if (isset($submissions) && $submissions->isNotEmpty())
        <section class="panel submission-history">
            <div class="panel-heading"><h2>{{ __('ui.author.history') }}</h2><span class="muted">{{ __('ui.author.total_submissions', ['count' => $submissions->total()]) }}</span></div>
            <div class="submission-mini-list">
                @foreach ($submissions as $submission)
                    <div><span><strong>{{ $submission->title }}</strong><small>{{ $submission->created_at?->format('Y-m-d') }} @if ($submission->review_note) · {{ $submission->review_note }} @endif</small></span><span class="status {{ $submission->status === 'pending' ? 'pending' : ($submission->status === 'rejected' ? 'rejected' : '') }}">{{ ['pending' => __('ui.author.reviewing'), 'approved' => __('ui.author.approved'), 'rejected' => __('ui.author.needs_changes')][$submission->status] ?? __('ui.components.no_content') }}</span></div>
                @endforeach
            </div>
            <div class="pagination-wrap">{{ $submissions->links() }}</div>
        </section>
    @endif
</main>
@endsection
