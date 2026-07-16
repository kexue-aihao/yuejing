@extends('layouts.app')

@section('title', '阅境 · 投稿审核')

@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">CONTENT REVIEW</p><h1>投稿审核</h1><p>查看作者提交的作品，记录审核意见并决定是否发布。</p></div></div>
    <div class="dashboard-grid">
        @include('pages.admin._nav', ['active' => 'submissions'])
        <div class="dashboard-content">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
            <div class="filter-tabs admin-filters"><a class="{{ !request('status') ? 'active' : '' }}" href="{{ route('admin.submissions.index') }}">全部</a><a class="{{ request('status') === 'pending' ? 'active' : '' }}" href="{{ route('admin.submissions.index', ['status' => 'pending']) }}">待审核</a><a class="{{ request('status') === 'approved' ? 'active' : '' }}" href="{{ route('admin.submissions.index', ['status' => 'approved']) }}">已批准</a><a class="{{ request('status') === 'rejected' ? 'active' : '' }}" href="{{ route('admin.submissions.index', ['status' => 'rejected']) }}">已拒绝</a></div>
            @forelse ($submissions as $submission)
                <article class="panel submission-review-card">
                    <div class="panel-heading"><div><h2>{{ $submission->title }}</h2><p class="panel-description">{{ $submission->user?->name ?? '匿名用户' }} · {{ $submission->user?->email ?? '无邮箱' }} · 提交于 {{ $submission->created_at?->format('Y-m-d H:i') }}</p></div><span class="status {{ $submission->status === 'pending' ? 'pending' : ($submission->status === 'rejected' ? 'rejected' : '') }}">{{ ['pending' => '待审核', 'approved' => '已批准', 'rejected' => '已拒绝'][$submission->status] ?? $submission->status }}</span></div>
                    @if ($submission->synopsis)<p class="submission-synopsis">{{ $submission->synopsis }}</p>@endif
                    <details class="manuscript-details"><summary>查看首章稿件</summary><div class="manuscript-preview">{{ $submission->manuscript }}</div></details>
                    @if ($submission->status === 'pending')
                        <form class="review-form" method="POST" action="{{ route('admin.submissions.review', $submission) }}">@csrf @method('PUT')<div class="form-field"><label for="review_note_{{ $submission->id }}">审核意见</label><textarea id="review_note_{{ $submission->id }}" name="review_note" rows="3" placeholder="给作者留下清晰、具体的反馈"></textarea></div><div class="review-actions"><button class="button button-primary" name="status" value="approved" type="submit">批准并发布</button><button class="button button-outline" name="status" value="rejected" type="submit">拒绝投稿</button></div></form>
                    @elseif ($submission->review_note)
                        <p class="review-note"><strong>审核意见：</strong>{{ $submission->review_note }}</p>
                    @endif
                </article>
            @empty
                <div class="empty-state"><h2>没有符合条件的投稿</h2><p>调整筛选条件后再试。</p></div>
            @endforelse
            <div class="pagination-wrap">{{ $submissions->links() }}</div>
        </div>
    </div>
</main>
@endsection
