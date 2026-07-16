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
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            <div class="metric-grid metric-grid-four">
                <div class="metric-card"><span>注册用户</span><strong>{{ $users }}</strong><small>全部账户</small></div>
                <div class="metric-card"><span>小说作品</span><strong>{{ $novels }}</strong><small>站内内容</small></div>
                <div class="metric-card"><span>章节总数</span><strong>{{ $chapters }}</strong><small>包含草稿</small></div>
                <div class="metric-card"><span>待审核投稿</span><strong>{{ $pending_submissions }}</strong><small><a href="{{ route('admin.submissions.index', ['status' => 'pending']) }}">进入审核</a></small></div>
            </div>
            <section class="panel">
                <div class="panel-heading"><h2>最近投稿</h2><a href="{{ route('admin.submissions.index') }}">查看全部 →</a></div>
                <div class="data-list">
                    @forelse ($recent_submissions as $submission)
                        <div class="data-list-row"><span><strong>{{ $submission->title }}</strong><small class="row-subtitle">{{ $submission->user?->name ?? '匿名用户' }} · {{ $submission->created_at?->format('Y-m-d H:i') }}</small></span><span class="status {{ $submission->status === 'pending' ? 'pending' : ($submission->status === 'rejected' ? 'rejected' : '') }}">{{ ['pending' => '待审核', 'approved' => '已批准', 'rejected' => '已拒绝'][$submission->status] ?? $submission->status }}</span><a class="text-button" href="{{ route('admin.submissions.index') }}">处理</a></div>
                    @empty
                        <div class="empty-state compact"><p>暂时没有投稿记录。</p></div>
                    @endforelse
                </div>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>最近作品</h2><a href="{{ route('admin.novels.index') }}">管理小说 →</a></div>
                <div class="data-list">
                    @forelse ($recent_novels as $novel)
                        <div class="data-list-row"><span><strong>{{ $novel->title }}</strong><small class="row-subtitle">{{ $novel->author?->name ?? '匿名作者' }} · {{ $novel->created_at?->format('Y-m-d') }}</small></span><span class="status {{ $novel->status === 'draft' ? 'pending' : '' }}">{{ ['draft' => '草稿', 'published' => '已发布', 'archived' => '已归档'][$novel->status] ?? $novel->status }}</span><a class="text-button" href="{{ route('admin.novels.index') }}">编辑</a></div>
                    @empty
                        <div class="empty-state compact"><p>暂时没有小说作品。</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
