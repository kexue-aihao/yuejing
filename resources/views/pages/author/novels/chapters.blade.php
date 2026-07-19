@extends('layouts.app')

@section('title', __('ui.author.chapters_title').' · '.$novel->title)

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div><p class="eyebrow">{{ __('ui.author.chapters_eyebrow') }}</p><h1>{{ $novel->title }}</h1><p>{{ __('ui.author.chapters_intro') }}</p></div>
        @if (Route::has('author.novels.edit'))<a class="button button-outline" href="{{ route('author.novels.edit', $novel) }}">{{ __('ui.author.edit_work') }}</a>@endif
    </div>

    <div class="dashboard-grid">
        <x-account-nav active="author-chapters" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><div><p class="panel-kicker">{{ __('ui.author.chapter_workspace') }}</p><h2>{{ __('ui.author.new_chapter') }}</h2></div></div>
                <form class="form-stack" method="POST" action="{{ route('author.chapters.store', $novel) }}" enctype="multipart/form-data" data-chapter-manuscript-form>@csrf
                    <input type="hidden" name="content_format" value="markdown" data-manuscript-format>
                    <div class="settings-grid"><div class="form-field"><label for="chapter_number">{{ __('ui.author.chapter_number') }}</label><input id="chapter_number" name="chapter_number" type="number" min="1" value="{{ old('chapter_number', $chapters->total() + 1) }}" required></div><div class="form-field"><label for="new_chapter_title">{{ __('ui.author.chapter_title') }}</label><input id="new_chapter_title" name="title" value="{{ old('title') }}" required></div><div class="form-field"><label for="new_chapter_status">{{ __('ui.author.chapter_status') }}</label><select id="new_chapter_status" name="status"><option value="draft">{{ __('ui.status.draft') }}</option><option value="published">{{ __('ui.status.published') }}</option></select></div></div>
                    <div class="form-field"><label for="new_chapter_content">{{ __('ui.author.chapter_content') }}</label><textarea id="new_chapter_content" name="content" data-manuscript-content rows="12">{{ old('content') }}</textarea><label for="new_chapter_file">{{ __('ui.author.upload_manuscript') }}</label><input id="new_chapter_file" name="chapter_file" type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" data-manuscript-file><span class="form-help" data-manuscript-file-name aria-live="polite"></span><p class="form-help">{{ __('ui.author.upload_manuscript_help') }}</p></div>
                    <button class="button button-primary" type="submit">{{ __('ui.author.save_chapter') }}</button>
                </form>
            </section>

            @forelse ($chapters as $chapter)
                <section class="panel author-chapter-card">
                    <form class="form-stack" method="POST" action="{{ route('author.chapters.update', [$novel, $chapter]) }}" enctype="multipart/form-data" data-chapter-manuscript-form>@csrf @method('PUT')
                        <input type="hidden" name="content_format" value="{{ $chapter->content_format ?? 'markdown' }}" data-manuscript-format>
                        <div class="panel-heading"><div><p class="panel-kicker">{{ __('ui.author.chapter_prefix', ['number' => $chapter->chapter_number]) }}</p><h2>{{ $chapter->title }}</h2></div><span class="status {{ $chapter->status === 'draft' ? 'pending' : '' }}">{{ __('ui.status.'.$chapter->status) }}</span></div>
                        <div class="settings-grid"><div class="form-field"><label for="chapter_number_{{ $chapter->id }}">{{ __('ui.author.chapter_number') }}</label><input id="chapter_number_{{ $chapter->id }}" name="chapter_number" type="number" min="1" value="{{ $chapter->chapter_number }}" required></div><div class="form-field"><label for="chapter_title_{{ $chapter->id }}">{{ __('ui.author.chapter_title') }}</label><input id="chapter_title_{{ $chapter->id }}" name="title" value="{{ $chapter->title }}" required></div><div class="form-field"><label for="chapter_status_{{ $chapter->id }}">{{ __('ui.author.chapter_status') }}</label><select id="chapter_status_{{ $chapter->id }}" name="status"><option value="draft" @selected($chapter->status === 'draft')>{{ __('ui.status.draft') }}</option><option value="published" @selected($chapter->status === 'published')>{{ __('ui.status.published') }}</option></select></div></div>
                        <div class="form-field"><label for="chapter_content_{{ $chapter->id }}">{{ __('ui.author.chapter_content') }}</label><textarea id="chapter_content_{{ $chapter->id }}" name="content" data-manuscript-content rows="12">{{ $chapter->content }}</textarea><label for="chapter_file_{{ $chapter->id }}">{{ __('ui.author.upload_manuscript') }}</label><input id="chapter_file_{{ $chapter->id }}" name="chapter_file" type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" data-manuscript-file><span class="form-help" data-manuscript-file-name aria-live="polite"></span><p class="form-help">{{ __('ui.author.upload_manuscript_help') }}</p></div>
                        <div class="card-actions"><button class="button button-primary button-small" type="submit">{{ __('ui.author.save_chapter') }}</button><button class="button button-outline button-small" type="submit" form="delete-chapter-{{ $chapter->id }}">{{ __('ui.author.delete_chapter') }}</button></div>
                    </form>
                    <form id="delete-chapter-{{ $chapter->id }}" method="POST" action="{{ route('author.chapters.destroy', [$novel, $chapter]) }}" class="sr-only">@csrf @method('DELETE')</form>
                </section>
            @empty
                <div class="empty-state"><h2>{{ __('ui.author.no_chapters') }}</h2><p>{{ __('ui.author.no_chapters_intro') }}</p></div>
            @endforelse
            @if (method_exists($chapters, 'links'))<div class="pagination-wrap">{{ $chapters->links() }}</div>@endif
        </div>
    </div>
</main>
@endsection
