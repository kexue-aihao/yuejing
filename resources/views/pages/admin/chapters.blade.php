@extends('layouts.app')
@section('title', '阅境 · 章节排版')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">CHAPTER STUDIO</p><h1>{{ $novel->title }}</h1><p>编辑章节正文、编号和发布状态。</p></div><a class="button button-outline" href="{{ route('admin.novels.index') }}">返回小说管理</a></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'novels'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><h2>新增章节</h2></div>
                <form class="form-stack" method="POST" action="{{ route('admin.chapters.store', $novel) }}">@csrf
                    <div class="settings-grid"><div class="form-field"><label for="chapter_number">章节号</label><input id="chapter_number" name="chapter_number" type="number" min="1" required></div><div class="form-field"><label for="title">章节标题</label><input id="title" name="title" required></div><div class="form-field"><label for="status">状态</label><select id="status" name="status"><option value="draft">草稿</option><option value="published">发布</option></select></div></div>
                    <div class="form-field"><label for="content">正文</label><textarea id="content" name="content" rows="12" required></textarea></div>
                    <button class="button button-primary" type="submit">保存章节</button>
                </form>
            </section>
            @foreach ($chapters as $chapter)
                <section class="panel">
                    <form class="form-stack" method="POST" action="{{ route('admin.chapters.update', [$novel, $chapter]) }}">@csrf @method('PUT')
                        <div class="panel-heading"><h2>第 {{ $chapter->chapter_number }} 章 · {{ $chapter->title }}</h2><span class="status {{ $chapter->status === 'draft' ? 'pending' : '' }}">{{ $chapter->status === 'published' ? '已发布' : '草稿' }}</span></div>
                        <div class="settings-grid"><div class="form-field"><label>章节号</label><input name="chapter_number" type="number" min="1" value="{{ $chapter->chapter_number }}" required></div><div class="form-field"><label>标题</label><input name="title" value="{{ $chapter->title }}" required></div><div class="form-field"><label>状态</label><select name="status"><option value="draft" @selected($chapter->status === 'draft')>草稿</option><option value="published" @selected($chapter->status === 'published')>发布</option></select></div></div>
                        <div class="form-field"><label>正文</label><textarea name="content" rows="12" required>{{ $chapter->content }}</textarea></div>
                        <div class="card-actions"><button class="button button-primary button-small" type="submit">保存章节</button></div>
                    </form>
                </section>
            @endforeach
            <div class="pagination-wrap">{{ $chapters->links() }}</div>
        </div>
    </div>
</main>
@endsection
