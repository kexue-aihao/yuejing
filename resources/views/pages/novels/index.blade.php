@extends('layouts.app')

@php
    $demoNovels = [
        ['title' => '潮汐之上', 'author' => '林渡', 'genre' => '都市 · 治愈', 'chapters' => 128, 'status' => '连载中', 'cover_a' => '#173f4a', 'cover_b' => '#d6a85b', 'slug' => 'chaoxi-zhi-shang'],
        ['title' => '长安有雪', 'author' => '白栩', 'genre' => '古言 · 权谋', 'chapters' => 96, 'status' => '已完结', 'cover_a' => '#4c3148', 'cover_b' => '#d88979', 'slug' => 'changan-you-xue'],
        ['title' => '星河失物招领处', 'author' => '迟也', 'genre' => '科幻 · 群像', 'chapters' => 74, 'status' => '连载中', 'cover_a' => '#293760', 'cover_b' => '#e0bd7c', 'slug' => 'xinghe-shiwuzhaolingchu'],
        ['title' => '春日失控', 'author' => '闻鹤', 'genre' => '青春 · 甜宠', 'chapters' => 31, 'status' => '连载中', 'cover_a' => '#995e4d', 'cover_b' => '#f1c8a7', 'slug' => 'chunri-shikong'],
        ['title' => '雾中来信', 'author' => '周折', 'genre' => '悬疑 · 现代', 'chapters' => 18, 'status' => '连载中', 'cover_a' => '#36444c', 'cover_b' => '#a8bfbd', 'slug' => 'wuzhong-laixin'],
        ['title' => '借一盏月光', 'author' => '南枝', 'genre' => '古言 · 轻喜', 'chapters' => 22, 'status' => '已完结', 'cover_a' => '#5c4b36', 'cover_b' => '#e6c88e', 'slug' => 'jie-yizhan-yueguang'],
        ['title' => '漫长的告白', 'author' => '顾栖迟', 'genre' => '言情 · 成长', 'chapters' => 27, 'status' => '连载中', 'cover_a' => '#6b3542', 'cover_b' => '#e6a6a4', 'slug' => 'manchang-de-gaobai'],
        ['title' => '无人区来客', 'author' => '季行舟', 'genre' => '科幻 · 冒险', 'chapters' => 58, 'status' => '连载中', 'cover_a' => '#35444c', 'cover_b' => '#c3a06e', 'slug' => 'wurenqu-laike'],
    ];
    $novelItems = isset($novels) && is_iterable($novels) && count($novels) ? $novels : $demoNovels;
@endphp

@section('title', '书库 · 阅境')
@section('content')
<main>
    <section class="page-band"><div class="site-shell"><p class="eyebrow">THE LIBRARY</p><h1>找到下一本<br>想读完的书。</h1><p class="page-intro">从不同的世界、情绪和人生里，挑一段现在的你刚好需要的故事。</p></div></section>
    <section class="site-shell page-content">
        <div class="library-toolbar"><nav class="filter-tabs" aria-label="作品筛选"><a class="{{ request('genre') ? '' : 'active' }}" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">全部</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '都市']) : '#' }}">都市</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '古言']) : '#' }}">古言</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '悬疑']) : '#' }}">悬疑</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '科幻']) : '#' }}">科幻</a><a href="{{ Route::has('novels.index') ? route('novels.index', ['genre' => '青春']) : '#' }}">青春</a></nav><form method="get" action="{{ Route::has('novels.index') ? route('novels.index') : '#' }}"><label class="sr-only" for="sort">排序方式</label><select id="sort" name="sort" class="sort-select" onchange="this.form.submit()"><option value="new" @selected(request('sort', 'new') === 'new')>按最新更新</option><option value="hot" @selected(request('sort') === 'hot')>按热度排序</option><option value="finished" @selected(request('sort') === 'finished')>只看完结</option></select></form></div>
        @if (count($novelItems))<div class="library-grid">@foreach ($novelItems as $book)<article class="library-card"><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug', data_get($book, 'id', 'demo'))]) : '#' }}"><x-book-cover :book="is_array($book) ? $book : ['title' => data_get($book, 'title', '未命名作品'), 'author' => data_get($book, 'author', '匿名作者'), 'cover_a' => data_get($book, 'cover_a', '#355c5d'), 'cover_b' => data_get($book, 'cover_b', '#d6aa67')]" size="large" /></a><h3><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => data_get($book, 'slug', data_get($book, 'id', 'demo'))]) : '#' }}">{{ data_get($book, 'title', '未命名作品') }}</a></h3><div class="library-card-meta"><span>{{ data_get($book, 'author', '匿名作者') }}</span><span>{{ data_get($book, 'chapters', 0) }} 章 · {{ data_get($book, 'status', '连载中') }}</span></div></article>@endforeach</div>@else<div class="empty-state"><h2>书架还是空的</h2><p>新故事很快就会来到这里，先去首页看看编辑精选吧。</p><a class="button button-primary" href="{{ Route::has('home') ? route('home') : url('/') }}">返回首页</a></div>@endif
    </section>
</main>
@endsection