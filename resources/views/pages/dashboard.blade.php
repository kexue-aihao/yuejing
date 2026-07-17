@extends('layouts.app')

@section('title', '我的阅境 · 阅境')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">MY YUEJING</p>
            <h1>你好，{{ auth()->user()->name ?? '读者' }}</h1>
            <p>欢迎回来，今天也为自己留一点阅读时间。</p>
        </div>
        <a class="button button-primary" href="{{ route('novels.index') }}">发现新故事 <span>→</span></a>
    </div>

    <div class="dashboard-grid">
        <x-account-nav active="dashboard" />

        <div class="dashboard-content">
            @if (session('status'))<div class="alert">{{ session('status') }}</div>@endif
            <div class="metric-grid">
                <x-metric-card label="阅读作品" :value="$readingCount" />
                <x-metric-card label="收藏作品" :value="$favoriteCount" />
                <x-metric-card label="投稿作品" :value="$submissionCounts->sum()" />
            </div>

            <section class="panel" id="history">
                <div class="panel-heading"><h2>继续阅读</h2><a href="{{ route('account.reading-records') }}">查看全部 →</a></div>
                @if ($reading->isNotEmpty())
                    <div class="data-list">
                        @foreach ($reading as $book)
                            <div class="data-list-row">
                                <span><strong>{{ $book['title'] }}</strong><br><small class="muted">{{ $book['author'] }} · {{ $book['status'] }}</small></span>
                                <span>{{ $book['progress'] }}</span>
                                @if ($book['slug'])
                                    <a class="text-link" href="{{ route('novels.read', ['novel' => $book['slug'], 'chapter' => $book['chapter']]) }}">继续 <span>→</span></a>
                                @else
                                    <span class="muted">作品已下架</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty-state icon="📖" message="还没有阅读记录，去书库打开第一章吧。" compact>
                        <a class="button button-dark" href="{{ route('novels.index') }}">去书库逛逛</a>
                    </x-empty-state>
                @endif
            </section>

            <section class="panel" id="favorites">
                <div class="panel-heading"><h2>我的收藏</h2><a href="{{ route('account.favorites') }}">查看全部 →</a></div>
                @if ($favorites->isNotEmpty())
                    <div class="account-book-list">
                        @foreach ($favorites as $favorite)
                            @if ($favorite->novel)
                                <a class="account-book-row" href="{{ route('novels.show', $favorite->novel->slug) }}">
                                    <span class="account-book-mark">阅</span>
                                    <span><strong>{{ $favorite->novel->title }}</strong><small>{{ $favorite->novel->author?->name ?? '匿名作者' }}</small></span>
                                    <span class="text-link">打开 <span>→</span></span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <x-empty-state icon="♡" message="收藏喜欢的作品后，它们会出现在这里。" compact>
                        <a class="button button-dark" href="{{ route('novels.index') }}">去书库逛逛</a>
                    </x-empty-state>
                @endif
            </section>

            @if (auth()->user()->isRole(['author', 'editor', 'admin']))
                <section class="panel" id="author-summary">
                    <div class="panel-heading"><h2>作品投稿</h2><a href="{{ route('author.submissions') }}">进入作品投稿 →</a></div>
                    <div class="submission-summary">
                        <div><span>待审核</span><strong>{{ $submissionCounts->get('pending', 0) }}</strong></div>
                        <div><span>已通过</span><strong>{{ $submissionCounts->get('approved', 0) }}</strong></div>
                        <div><span>需修改</span><strong>{{ $submissionCounts->get('rejected', 0) }}</strong></div>
                    </div>
                    @if ($submissions->isNotEmpty())
                        <div class="submission-mini-list">
                            @foreach ($submissions as $submission)
                                <div><span>{{ $submission->title }}</span><x-status-badge :status="$submission->status" /></div>
                            @endforeach
                        </div>
                    @else
                        <p class="muted dashboard-note">还没有投稿，写下你的第一个故事。</p>
                    @endif
                </section>
            @endif
        </div>
    </div>
</main>
@endsection
