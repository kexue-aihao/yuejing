@extends('layouts.app')
@section('title', '阅境 · 投稿审计日志')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head">
        <div>
            <p class="eyebrow">SUBMISSION AUDIT</p>
            <h1>投稿审计日志</h1>
            <p>只记录投稿提交、批准和拒绝，追踪作品审核的完整链路。</p>
        </div>
    </div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'audit-logs'])
        <div class="dashboard-content">
            <section class="panel submission-audit-panel">
                <div class="submission-audit-list">
                    @forelse ($logs as $log)
                        @php
                            $submission = $log->auditable;
                            $metadata = $log->metadata ?? [];
                            $actionLabels = [
                                'submission.created' => '投稿提交',
                                'submission.approved' => '批准发布',
                                'submission.rejected' => '拒绝投稿',
                            ];
                        @endphp
                        <article class="submission-audit-row">
                            <div class="submission-audit-main">
                                <div class="submission-audit-heading">
                                    <strong>{{ $actionLabels[$log->action] ?? $log->action }}</strong>
                                    <span class="submission-audit-id">投稿 #{{ $log->auditable_id }}</span>
                                </div>
                                <h2>{{ $submission?->title ?? ($metadata['title'] ?? '投稿已删除') }}</h2>
                                <p class="submission-audit-meta">
                                    作者：{{ $submission?->user?->name ?? '未知作者' }}
                                    @if($submission?->category?->name) · 分类：{{ $submission->category->name }} @endif
                                    · 操作人：{{ $log->user?->name ?? '系统' }}
                                </p>
                                @if(!empty($metadata['review_note']))
                                    <p class="submission-audit-note">审核意见：{{ $metadata['review_note'] }}</p>
                                @endif
                            </div>
                            <div class="submission-audit-side">
                                <time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('Y-m-d H:i:s') }}</time>
                                <span>投稿来源 IP：{{ $log->ip_address ?? '未知 IP' }}</span>
                            </div>
                        </article>
                    @empty
                        <x-empty-state icon="📋" message="暂无投稿审计记录。" compact />
                    @endforelse
                </div>
                <div class="pagination-wrap">{{ $logs->links() }}</div>
            </section>
        </div>
    </div>
</main>
@endsection
