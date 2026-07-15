@extends('layouts.app')

@php
    $featured = [
        ['title' => '潮汐之上', 'author' => '林渡', 'genre' => '都市 · 治愈', 'desc' => '她在每一次潮汐里寻找失散多年的答案，也终于学会与自己和解。', 'chapters' => 128, 'updated' => '刚刚更新', 'cover_a' => '#173f4a', 'cover_b' => '#d6a85b', 'slug' => 'chaoxi-zhi-shang'],
        ['title' => '长安有雪', 'author' => '白栩', 'genre' => '古言 · 权谋', 'desc' => '雪落长安时，旧日盟约和少年心事一同被重新翻开。', 'chapters' => 96, 'updated' => '今日更新', 'cover_a' => '#4c3148', 'cover_b' => '#d88979', 'slug' => 'changan-you-xue'],
        ['title' => '星河失物招领处', 'author' => '迟也', 'genre' => '科幻 · 群像', 'desc' => '在银河尽头经营一家小店，替旅人找回被遗忘的珍贵之物。', 'chapters' => 74, 'updated' => '昨日更新', 'cover_a' => '#293760', 'cover_b' => '#e0bd7c', 'slug' => 'xinghe-shiwuzhaolingchu'],
    ];
    $newBooks = [
        ['title' => '春日失控', 'author' => '闻鹤', 'genre' => '青春 · 甜宠', 'chapters' => 31, 'cover_a' => '#995e4d', 'cover_b' => '#f1c8a7', 'slug' => 'chunri-shikong'],
        ['title' => '雾中来信', 'author' => '周折', 'genre' => '悬疑 · 现代', 'chapters' => 18, 'cover_a' => '#36444c', 'cover_b' => '#a8bfbd', 'slug' => 'wuzhong-laixin'],
        ['title' => '借一盏月光', 'author' => '南枝', 'genre' => '古言 · 轻喜', 'chapters' => 22, 'cover_a' => '#5c4b36', 'cover_b' => '#e6c88e', 'slug' => 'jie-yizhan-yueguang'],
        ['title' => '漫长的告白', 'author' => '顾栖迟', 'genre' => '言情 · 成长', 'chapters' => 27, 'cover_a' => '#6b3542', 'cover_b' => '#e6a6a4', 'slug' => 'manchang-de-gaobai'],
    ];
    $ranking = [
        ['title' => '借一盏月光', 'author' => '南枝', 'score' => '9.8', 'tag' => '古言', 'slug' => 'jie-yizhan-yueguang'],
        ['title' => '潮汐之上', 'author' => '林渡', 'score' => '9.7', 'tag' => '都市', 'slug' => 'chaoxi-zhi-shang'],
        ['title' => '无人区来客', 'author' => '季行舟', 'score' => '9.6', 'tag' => '科幻', 'slug' => 'wurenqu-laike'],
        ['title' => '春日失控', 'author' => '闻鹤', 'score' => '9.5', 'tag' => '青春', 'slug' => 'chunri-shikong'],
        ['title' => '长安有雪', 'author' => '白栩', 'score' => '9.4', 'tag' => '古言', 'slug' => 'changan-you-xue'],
    ];
@endphp

@section('title', '阅境 · 在故事里相遇')
@section('content')
<main>
    <section class="hero-band"><div class="site-shell hero-grid"><div class="hero-copy"><p class="eyebrow"><span class="eyebrow-mark"></span> 今日好故事，正在发生</p><h1>在阅境，<br><em>与故事相遇。</em></h1><p class="hero-lead">给忙碌的日子留一盏灯。这里有被认真写下的人生，也有值得慢慢读完的心事。</p><div class="hero-actions"><a class="button button-primary" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">开始阅读 <span aria-hidden="true">↗</span></a><a class="text-link" href="#featured">看看本周精选 <span aria-hidden="true">↓</span></a></div><div class="hero-meta" aria-label="阅境数据"><span><strong>2,480+</strong> 部作品</span><span><strong>36万+</strong> 位读者</span><span><strong>每日</strong> 更新</span></div></div><div class="hero-stage" aria-label="精选小说预览"><div class="stage-note stage-note-top">EDITOR'S NOTE <span>01</span></div><div class="stage-book stage-book-back"><span>长安<br>有雪</span></div><div class="stage-book stage-book-main"><span>潮汐<br>之上</span><small>林渡 著</small></div><div class="stage-note stage-note-bottom"><span>本周编辑推荐</span> READ SLOWLY</div><div class="stage-line stage-line-one"></div><div class="stage-line stage-line-two"></div></div></div></section>
    <section id="featured" class="site-shell section-block"><div class="section-heading"><div><p class="eyebrow">精选阅读</p><h2>这一刻，值得翻开</h2></div><a class="text-link" href="{{ Route::has('novels.index') ? route('novels.index') : '#' }}">全部精选 <span>→</span></a></div><div class="featured-grid">@foreach ($featured as $index => $book)<article class="featured-card {{ $index === 0 ? 'featured-card-primary' : '' }}"><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => $book['slug']]) : '#' }}" class="cover-link">@include('partials.book-cover', ['book' => $book, 'size' => 'large'])</a><div class="featured-card-copy"><p class="book-kicker">{{ $book['genre'] }}</p><h3><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => $book['slug']]) : '#' }}">{{ $book['title'] }}</a></h3><p>{{ $book['desc'] }}</p><div class="book-byline"><span>{{ $book['author'] }}</span><span>{{ $book['chapters'] }} 章 · {{ $book['updated'] }}</span></div></div></article>@endforeach</div></section>
    <section class="site-shell section-block section-divider"><div class="section-heading"><div><p class="eyebrow">刚刚上新</p><h2>新的故事，新的入口</h2></div><a class="text-link" href="{{ Route::has('novels.index') ? route('novels.index', ['sort' => 'new']) : '#' }}">发现更多 <span>→</span></a></div><div class="new-books-grid">@foreach ($newBooks as $book)<article class="book-row"><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => $book['slug']]) : '#' }}">@include('partials.book-cover', ['book' => $book, 'size' => 'small'])</a><div class="book-row-copy"><p class="book-kicker">{{ $book['genre'] }}</p><h3><a href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => $book['slug']]) : '#' }}">{{ $book['title'] }}</a></h3><p class="muted">{{ $book['author'] }}</p><span class="chapter-count">更新至 {{ $book['chapters'] }} 章</span></div></article>@endforeach</div></section>
    <section class="rank-band"><div class="site-shell ranking-layout"><div class="rank-intro"><p class="eyebrow">阅境热度榜</p><h2>大家都在读<br><em>哪一本？</em></h2><p>从今天的阅读偏好里，找到下一本让你舍不得放下的书。</p><a class="button button-dark" href="{{ Route::has('novels.index') ? route('novels.index', ['sort' => 'hot']) : '#' }}">查看完整榜单 <span>→</span></a></div><div class="ranking-list">@foreach ($ranking as $index => $book)<a class="ranking-item" href="{{ Route::has('novels.show') ? route('novels.show', ['novel' => $book['slug']]) : '#' }}"><span class="rank-number">0{{ $index + 1 }}</span><span class="rank-title"><strong>{{ $book['title'] }}</strong><small>{{ $book['author'] }} · {{ $book['tag'] }}</small></span><span class="rank-score">{{ $book['score'] }}<small>评分</small></span></a>@endforeach</div></div></section>
    <section id="categories" class="site-shell category-strip"><p class="eyebrow">按心情找书</p><div class="category-links"><a href="#">都市情感 <span>238</span></a><a href="#">古风言情 <span>186</span></a><a href="#">悬疑推理 <span>92</span></a><a href="#">科幻世界 <span>64</span></a><a href="#">青春成长 <span>121</span></a></div></section>
</main>
@endsection