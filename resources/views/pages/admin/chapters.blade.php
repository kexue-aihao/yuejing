@extends('layouts.app')
@section('title', __('ui.admin.chapter_eyebrow').' · '.__('ui.admin.title_suffix'))
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.admin.chapter_eyebrow') }}</p><h1>{{ $novel->title }}</h1><p>{{ __('ui.admin.chapter_intro') }}</p></div><a class="button button-outline" href="{{ route('admin.novels.index') }}">{{ __('ui.admin.back_to_novels') }}</a></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'novels'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><h2>{{ __('ui.admin.new_chapter') }}</h2></div>
                <form class="form-stack" method="POST" action="{{ route('admin.chapters.store', $novel) }}">@csrf
                    <div class="settings-grid"><div class="form-field"><label for="chapter_number">{{ __('ui.admin.chapter_number') }}</label><input id="chapter_number" name="chapter_number" type="number" min="1" required></div><div class="form-field"><label for="title">{{ __('ui.admin.chapter_title') }}</label><input id="title" name="title" required></div><div class="form-field"><label for="status">{{ __('ui.admin.status') }}</label><select id="status" name="status"><option value="draft">{{ __('ui.admin.draft') }}</option><option value="published">{{ __('ui.admin.publish') }}</option></select></div></div>
                    <div class="form-field"><label for="content">{{ __('ui.admin.content') }}</label><textarea id="content" name="content" rows="12" required></textarea></div>
                    <button class="button button-primary" type="submit">{{ __('ui.admin.save_chapter') }}</button>
                </form>
            </section>
            @foreach ($chapters as $chapter)
                <section class="panel">
                    <form class="form-stack" method="POST" action="{{ route('admin.chapters.update', [$novel, $chapter]) }}">@csrf @method('PUT')
                        <div class="panel-heading"><h2>{{ __('ui.novel_detail.chapter_prefix', ['number' => $chapter->chapter_number]) }} · {{ $chapter->title }}</h2><span class="status {{ $chapter->status === 'draft' ? 'pending' : '' }}">{{ $chapter->status === 'published' ? __('ui.admin.published') : __('ui.admin.draft') }}</span></div>
                        <div class="settings-grid"><div class="form-field"><label>{{ __('ui.admin.chapter_number') }}</label><input name="chapter_number" type="number" min="1" value="{{ $chapter->chapter_number }}" required></div><div class="form-field"><label>{{ __('ui.admin.chapter_title') }}</label><input name="title" value="{{ $chapter->title }}" required></div><div class="form-field"><label>{{ __('ui.admin.status') }}</label><select name="status"><option value="draft" @selected($chapter->status === 'draft')>{{ __('ui.admin.draft') }}</option><option value="published" @selected($chapter->status === 'published')>{{ __('ui.admin.publish') }}</option></select></div></div>
                        <div class="form-field"><label>{{ __('ui.admin.content') }}</label><textarea name="content" rows="12" required>{{ $chapter->content }}</textarea></div>
                        <div class="card-actions"><button class="button button-primary button-small" type="submit">{{ __('ui.admin.save_chapter') }}</button></div>
                    </form>
                </section>
            @endforeach
            <div class="pagination-wrap">{{ $chapters->links() }}</div>
        </div>
    </div>
</main>
@endsection
