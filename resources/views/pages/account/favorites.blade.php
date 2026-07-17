@extends('layouts.app')

@section('title', '我的收藏 · 阅境')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">MY LIBRARY</p><h1>我的收藏</h1><p>把想读的故事留在身边。</p></div><a class="button button-primary" href="{{ route('novels.index') }}">发现新故事 <span>→</span></a></div>
    <div class="dashboard-grid">
        <x-account-nav active="favorites" />
        <div class="dashboard-content">
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            <section class="panel">
                <div class="panel-heading"><h2>收藏作品</h2><span class="muted">共 {{ $favorites->total() }} 部</span></div>
                @if ($favorites->count() > 0)
                    <div class="account-book-list account-book-list-large">
                        @foreach ($favorites as $favorite)
                            @if ($favorite->novel)
                                <div class="account-book-row"><a class="account-book-main" href="{{ route('novels.show', $favorite->novel->slug) }}"><span class="account-book-mark">阅</span><span><strong>{{ $favorite->novel->title }}</strong><small>{{ $favorite->novel->author?->name ?? '匿名作者' }} · 收藏于 {{ $favorite->created_at?->format('Y-m-d') }}</small></span></a><form method="POST" action="{{ route('novels.unfavorite', $favorite->novel) }}">@csrf @method('DELETE')<button class="text-button" type="submit">取消收藏</button></form></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="pagination-wrap">{{ $favorites->links() }}</div>
                @else
                    <div class="empty-state"><h2>还没有收藏</h2><p>遇到喜欢的故事，就把它留在这里。</p><a class="button button-dark" href="{{ route('novels.index') }}">浏览书库</a></div>
                @endif
            </section>
        </div>
    </div>
</main>
@endsection
