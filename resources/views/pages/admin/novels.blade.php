@extends('layouts.app')

@section('title', __('ui.admin.novel_management').' · '.__('ui.admin.title_suffix'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.admin.library_eyebrow') }}</p><h1>{{ __('ui.admin.novel_management') }}</h1><p>{{ __('ui.admin.library_intro') }}</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'novels'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            @forelse ($novels as $novel)
                <article class="panel novel-admin-card">
                    <form method="POST" action="{{ route('admin.novels.update', $novel) }}">@csrf @method('PUT')
                        <div class="panel-heading"><div><h2>{{ $novel->title }}</h2><p class="panel-description">{{ $novel->author?->name ?? __('ui.admin.anonymous_author') }} · {{ $novel->slug }}</p></div><span class="status {{ $novel->status === 'draft' ? 'pending' : '' }}">{{ ['draft' => __('ui.admin.draft'), 'published' => __('ui.admin.published'), 'archived' => __('ui.admin.archived')][$novel->status] ?? __('ui.components.no_content') }}</span></div>
                        <div class="settings-grid">
                            <div class="form-field"><label for="title_{{ $novel->id }}">{{ __('ui.admin.work_title') }}</label><input id="title_{{ $novel->id }}" name="title" value="{{ old('title', $novel->title) }}" required></div>
                            <div class="form-field"><label for="status_{{ $novel->id }}">{{ __('ui.admin.work_status') }}</label><select id="status_{{ $novel->id }}" name="status"><option value="draft" @selected($novel->status === 'draft')>{{ __('ui.admin.draft') }}</option><option value="published" @selected($novel->status === 'published')>{{ __('ui.admin.published') }}</option><option value="archived" @selected($novel->status === 'archived')>{{ __('ui.admin.archived') }}</option></select></div>
                            <div class="form-field form-field-wide"><label for="synopsis_{{ $novel->id }}">{{ __('ui.admin.synopsis') }}</label><textarea id="synopsis_{{ $novel->id }}" name="synopsis" rows="3">{{ old('synopsis', $novel->synopsis) }}</textarea></div>
                        </div>
                        <div class="card-actions"><button class="button button-primary button-small" type="submit">{{ __('ui.admin.save_novel') }}</button><a class="button button-outline button-small" href="{{ route('admin.chapters.index', $novel) }}">{{ __('ui.admin.manage_chapters', ['count' => $novel->chapters_count]) }}</a></div>
                    </form>
                </article>
            @empty
                <div class="empty-state"><h2>{{ __('ui.admin.no_novels_yet') }}</h2><p>{{ __('ui.admin.approved_appear_here') }}</p></div>
            @endforelse
            <div class="pagination-wrap">{{ $novels->links() }}</div>
        </div>
    </div>
</main>
@endsection
