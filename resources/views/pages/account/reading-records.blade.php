@extends('layouts.app')

@section('title', '阅读记录 · 阅境')
@section('content')
<main class="site-shell dashboard-page">
    <div class="dashboard-head"><div><p class="eyebrow">READING HISTORY</p><h1>阅读记录</h1><p>从上次停下的地方，继续你的故事。</p></div><a class="button button-primary" href="{{ route('novels.index') }}">寻找新故事 <span>→</span></a></div>
    <div class="dashboard-grid">
        <x-account-nav active="reading-records" />
        <div class="dashboard-content">
            <section class="panel"><div class="panel-heading"><h2>最近阅读</h2><span class="muted">共 {{ $records->total() }} 条</span></div>
                @if ($records->count() > 0)
                    <div class="data-list history-list">
                        @foreach ($records as $record)
                            <div class="data-list-row"><span><strong>{{ $record->novel?->title ?? '未命名作品' }}</strong><br><small class="muted">{{ $record->novel?->author?->name ?? '匿名作者' }} · 阅读至 {{ (int) $record->progress }}% · {{ $record->last_read_at?->format('Y-m-d H:i') ?? '最近' }}</small></span><span>第 {{ $record->chapter?->chapter_number ?? 1 }} 章</span>@if ($record->novel)<a class="text-link" href="{{ route('novels.read', ['novel' => $record->novel->slug, 'chapter' => $record->chapter?->chapter_number ?? 1]) }}">继续 <span>→</span></a>@else<span class="muted">作品已下架</span>@endif</div>
                        @endforeach
                    </div>
                    <div class="pagination-wrap">{{ $records->links() }}</div>
                @else
                    <div class="empty-state"><h2>还没有阅读记录</h2><p>打开一本作品，阅读进度会自动保存在这里。</p><a class="button button-dark" href="{{ route('novels.index') }}">去书库逛逛</a></div>
                @endif
            </section>
        </div>
    </div>
</main>
@endsection
