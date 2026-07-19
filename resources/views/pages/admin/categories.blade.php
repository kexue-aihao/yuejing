@extends('layouts.app')

@section('title', __('ui.admin.category_management').' · '.__('ui.admin.title_suffix'))

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">{{ __('ui.admin.taxonomy_eyebrow') }}</p><h1>{{ __('ui.admin.category_management') }}</h1><p>{{ __('ui.admin.taxonomy_intro') }}</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'categories'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><h2>{{ __('ui.admin.new_category') }}</h2></div>
                <form class="inline-form category-create-form" method="POST" action="{{ route('admin.categories.store') }}">@csrf<div class="form-field"><label for="name">{{ __('ui.admin.category_name') }}</label><input id="name" name="name" value="{{ old('name') }}" required></div><div class="form-field"><label for="slug">{{ __('ui.admin.category_slug') }}</label><input id="slug" name="slug" value="{{ old('slug') }}" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required></div><button class="button button-primary" type="submit">{{ __('ui.admin.create_category') }}</button></form>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>{{ __('ui.admin.category_list') }}</h2><span class="muted">{{ __('ui.admin.category_count', ['count' => $categories->total()]) }}</span></div>
                <div class="data-list">
                    @forelse ($categories as $category)
                        <div class="data-list-row category-row"><form class="category-edit-form" method="POST" action="{{ route('admin.categories.update', $category) }}">@csrf @method('PUT')<input aria-label="{{ __('ui.admin.category_name') }}" name="name" value="{{ $category->name }}" required><input aria-label="{{ __('ui.admin.category_slug') }}" name="slug" value="{{ $category->slug }}" required><span class="muted">{{ __('ui.admin.novel_count', ['count' => $category->novels_count]) }}</span><button class="text-button" type="submit">{{ __('ui.admin.save') }}</button></form></div>
                    @empty
                        <div class="empty-state compact"><p>{{ __('ui.admin.no_categories') }}</p></div>
                    @endforelse
                </div>
                <div class="pagination-wrap">{{ $categories->links() }}</div>
            </section>
        </div>
    </div>
</main>
@endsection
