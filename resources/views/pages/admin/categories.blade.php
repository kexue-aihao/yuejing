@extends('layouts.app')

@section('title', '阅境 · 分类管理')

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">CONTENT TAXONOMY</p><h1>分类管理</h1><p>浏览和维护书库中的内容分类。</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'categories'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><h2>新增分类</h2></div>
                <form class="inline-form category-create-form" method="POST" action="{{ route('admin.categories.store') }}">@csrf<div class="form-field"><label for="name">分类名称</label><input id="name" name="name" value="{{ old('name') }}" required></div><div class="form-field"><label for="slug">Slug（可选）</label><input id="slug" name="slug" value="{{ old('slug') }}"></div><button class="button button-primary" type="submit">新增分类</button></form>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>分类列表</h2><span class="muted">共 {{ $categories->total() }} 个</span></div>
                <div class="data-list">
                    @forelse ($categories as $category)
                        <div class="data-list-row category-row"><form class="category-edit-form" method="POST" action="{{ route('admin.categories.update', $category) }}">@csrf @method('PUT')<input aria-label="分类名称" name="name" value="{{ $category->name }}" required><input aria-label="分类 slug" name="slug" value="{{ $category->slug }}" required><span class="muted">{{ $category->novels_count }} 部小说</span><button class="text-button" type="submit">保存</button></form></div>
                    @empty
                        <div class="empty-state compact"><p>暂时没有分类。</p></div>
                    @endforelse
                </div>
                <div class="pagination-wrap">{{ $categories->links() }}</div>
            </section>
        </div>
    </div>
</main>
@endsection
