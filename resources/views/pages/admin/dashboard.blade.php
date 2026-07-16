@extends('layouts.app')

@section('title', '阅境 · 管理控制台')

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div><p class="eyebrow">ADMIN CONSOLE</p><h1>管理控制台</h1><p>把站点运行、内容审核和阅读体验放在一个清晰的工作台里。</p></div>
        <span class="status">系统运行正常</span>
    </div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'dashboard'])
        <div class="dashboard-content">
            @if (session('status'))<x-alert type="success" :message="session('status')" dismissible />@endif
            <div class="metric-grid">
                <x-metric-card label="注册用户" :value="$users" />
                <x-metric-card label="小说作品" :value="$novels" />
                <x-metric-card label="章节总数" :value="$chapters" />
                <x-metric-card label="待审核投稿" :value="$pending_submissions" />
            </div>
            <section class="panel">
                <div class="panel-heading"><h2>最近投稿</h2><a href="{{ route('admin.submissions.index') }}">查看全部 →</a></div>
                <div class="data-list">
                    @forelse ($recent_submissions as $submission)
                        <div class="data-list-row"><span><strong>{{ $submission->title }}</strong><small class="row-subtitle">{{ $submission->user?->name ?? '匿名用户' }} · {{ $submission->created_at?->format('Y-m-d H:i') }}</small></span><x-status-badge :status="$submission->status" /><a class="text-button" href="{{ route('admin.submissions.index') }}">处理</a></div>
                    @empty
                        <x-empty-state icon="📋" message="暂时没有投稿记录。" compact />
                    @endforelse
                </div>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>最近作品</h2><a href="{{ route('admin.novels.index') }}">管理小说 →</a></div>
                <div class="data-list">
                    @forelse ($recent_novels as $novel)
                        <div class="data-list-row"><span><strong>{{ $novel->title }}</strong><small class="row-subtitle">{{ $novel->author?->name ?? '匿名作者' }} · {{ $novel->created_at?->format('Y-m-d') }}</small></span><x-status-badge :status="$novel->status" /><a class="text-button" href="{{ route('admin.novels.index') }}">编辑</a></div>
                    @empty
                        <x-empty-state icon="📚" message="暂时没有小说作品。" compact />
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
