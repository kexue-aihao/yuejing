@extends('layouts.app')
@section('title', '阅境 · 审计日志')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">AUDIT TRAIL</p><h1>审计日志</h1><p>查看登录、安全、设置和内容管理操作。</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'audit-logs'])
        <div class="dashboard-content"><section class="panel"><div class="data-list">
            @forelse ($logs as $log)
                <div class="data-list-row"><span><strong>{{ $log->action }}</strong><small class="row-subtitle">{{ $log->user?->name ?? '系统' }} · {{ $log->ip_address ?? '未知 IP' }}</small></span><span class="muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</span></div>
            @empty
                <div class="empty-state compact"><p>暂无审计记录。</p></div>
            @endforelse
        </div><div class="pagination-wrap">{{ $logs->links() }}</div></section></div>
    </div>
</main>
@endsection
