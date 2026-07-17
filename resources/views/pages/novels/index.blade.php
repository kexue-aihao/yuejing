@extends('layouts.app')

@php
    $novelItems = collect($novels ?? []);
@endphp

@section('title', '书库 · 阅境')
@section('content')
<main>
    <section class="page-band"><div class="site-shell"><p class="eyebrow">THE LIBRARY</p><h1>找到下一本<br>想读完的书。</h1><p class="page-intro">从不同的世界、情绪和人生里，挑一段现在的你刚好需要的故事。</p></div></section>
    <section class="site-shell page-content">
        <div class="library-toolbar"><nav class="filter-tabs" aria-label="作品筛选"><a class="{{ request('genre') ? '' : 'active' }}" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">全部</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '都市']) : '#' }}">都市</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '古言']) : '#' }}">古言</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '悬疑']) : '#' }}">悬疑</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '科幻']) : '#' }}">科幻</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '青春']) : '#' }}">青春</a></nav><form method="get" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}"><label class="sr-only" for="sort">排序方式</label><select id="sort" name="sort" class="sort-select" onchange="this.form.submit()"><option value="new" @selected(request('sort', 'new') === 'new')>按最新更新</option><option value="hot" @selected(request('sort') === 'hot')>按热度排序</option><option value="finished" @selected(request('sort') === 'finished')>只看完结</option></select></form></div>
        @if (count($novelItems))<div class="library-grid">@foreach ($novelItems as $book)<article class="library-card"><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug')]) : '#' }}"><x-book-cover :book="is_array($book) ? $book : ['title' => data_get($book, 'title', '未命名作品'), 'author' => data_get($book, 'author', '匿名作者'), 'cover_a' => data_get($book, 'cover_a', '#355c5d'), 'cover_b' => data_get($book, 'cover_b', '#d6aa67')]" size="large" /></a><h3><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug')]) : '#' }}">{{ data_get($book, 'title', '未命名作品') }}</a></h3><div class="library-card-meta"><span>{{ data_get($book, 'author', '匿名作者') }}</span><span>{{ data_get($book, 'chapters', 0) }} 章 · {{ data_get($book, 'status', '连载中') }}</span></div></article>@endforeach</div>@else<div class="empty-state"><h2>书架还是空的</h2><p>新故事很快就会来到这里，先去首页看看编辑精选吧。</p><a class="button button-primary" href="{{ Route::has('home') ? route('home') : url('/') }}">返回首页</a></div>@endif
    </section>
</main>
@endsection