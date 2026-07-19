@extends('layouts.app')

@section('title', __('ui.author.edit_title').' · '.$novel->title)

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div><p class="eyebrow">{{ __('ui.author.novels_eyebrow') }}</p><h1>{{ __('ui.author.edit_heading') }}</h1><p>{{ __('ui.author.edit_intro') }}</p></div>
        @if (Route::has('author.novels.index'))<a class="button button-outline" href="{{ route('author.novels.index') }}">{{ __('ui.author.back_to_novels') }}</a>@endif
    </div>

    <div class="dashboard-grid">
        <x-account-nav active="author-novel-edit" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <section class="panel">
                <form class="form-stack" method="POST" action="{{ route('author.novels.update', $novel) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="author-edit-layout">
                        <div class="author-edit-cover">
                            <p class="form-field-label">{{ __('ui.author.current_cover') }}</p>
                            <x-book-cover :book="['title' => $novel->title, 'author' => auth()->user()->name, 'cover_url' => $novel->cover_url]" size="large" />
                            <p class="form-help">{{ __('ui.author.current_cover_help') }}</p>
                        </div>
                        <div class="form-stack">
                            <div class="form-field"><label for="title">{{ __('ui.author.title_label') }}</label><input id="title" name="title" value="{{ old('title', $novel->title) }}" required></div>
                            <div class="form-field"><label for="category_id">{{ __('ui.author.genre_label') }}</label><select id="category_id" name="category_id" required><option value="">{{ __('ui.author.choose_category') }}</option>@foreach ($categories ?? [] as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $novel->categories?->first()?->id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                            <div class="form-field"><label for="status">{{ __('ui.author.work_status') }}</label><select id="status" name="status">@foreach (($statusOptions ?? ['draft', 'published', 'archived']) as $status)<option value="{{ is_string($status) ? $status : $status['value'] }}" @selected(old('status', $novel->status) === (is_string($status) ? $status : $status['value']))>{{ is_string($status) ? __('ui.status.'.$status) : ($status['label'] ?? $status['value']) }}</option>@endforeach</select></div>
                            <div class="form-field"><label for="synopsis">{{ __('ui.author.summary_label') }}</label><textarea id="synopsis" name="synopsis" rows="8" maxlength="5000">{{ old('synopsis', $novel->synopsis) }}</textarea></div>
                        </div>
                    </div>
                    <div class="form-field cover-upload-field"><label for="cover">{{ __('ui.author.replace_cover') }}</label><div class="cover-preview-control" data-vue-cover-preview data-input-id="cover" data-input-name="cover" data-accept="image/jpeg,image/png,image/webp" data-preview-alt="{{ __('ui.author.cover_label') }}" data-description-id="cover-edit-help"><input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" data-cover-input><img class="cover-upload-preview" data-cover-preview alt="{{ __('ui.author.cover_label') }}" hidden></div><p id="cover-edit-help" class="form-help">{{ __('ui.author.cover_edit_help') }}</p></div>
                    <div class="card-actions"><button class="button button-primary" type="submit">{{ __('ui.author.save_work') }}</button>@if (Route::has('author.chapters.index'))<a class="button button-outline" href="{{ route('author.chapters.index', $novel) }}">{{ __('ui.author.manage_chapters') }}</a>@endif</div>
                </form>
            </section>
        </div>
    </div>
</main>
@endsection
