@extends('layouts.app')

@section('title', '阅境 · 小说管理')

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">CONTENT LIBRARY</p><h1>小说管理</h1><p>维护作品状态，进入章节编辑和发布流程。</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'novels'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            @forelse ($novels as $novel)
                <article class="panel novel-admin-card">
                    <form method="POST" action="{{ route('admin.novels.update', $novel) }}">@csrf @method('PUT')
                        <div class="panel-heading"><div><h2>{{ $novel->title }}</h2><p class="panel-description">{{ $novel->author?->name ?? '匿名作者' }} · {{ $novel->slug }}</p></div><span class="status {{ $novel->status === 'draft' ? 'pending' : '' }}">{{ ['draft' => '草稿', 'published' => '已发布', 'archived' => '已归档'][$novel->status] ?? $novel->status }}</span></div>
                        <div class="settings-grid">
                            <div class="form-field"><label for="title_{{ $novel->id }}">作品名称</label><input id="title_{{ $novel->id }}" name="title" value="{{ old('title', $novel->title) }}" required></div>
                            <div class="form-field"><label for="status_{{ $novel->id }}">作品状态</label><select id="status_{{ $novel->id }}" name="status"><option value="draft" @selected($novel->status === 'draft')>草稿</option><option value="published" @selected($novel->status === 'published')>已发布</option><option value="archived" @selected($novel->status === 'archived')>已归档</option></select></div>
                            <div class="form-field form-field-wide"><label for="synopsis_{{ $novel->id }}">作品简介</label><textarea id="synopsis_{{ $novel->id }}" name="synopsis" rows="3">{{ old('synopsis', $novel->synopsis) }}</textarea></div>
                        </div>
                        <div class="card-actions"><button class="button button-primary button-small" type="submit">保存小说</button><a class="button button-outline button-small" href="{{ route('admin.chapters.index', $novel) }}">管理章节（{{ $novel->chapters_count }}）</a></div>
                    </form>
                </article>
            @empty
                <div class="empty-state"><h2>还没有小说</h2><p>批准投稿后，作品会出现在这里。</p></div>
            @endforelse
            <div class="pagination-wrap">{{ $novels->links() }}</div>
        </div>
    </div>
</main>
@endsection
