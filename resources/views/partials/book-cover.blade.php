@php
    $book = $book ?? [];
    $size = $size ?? 'small';
    $title = $book['title'] ?? '未命名作品';
    $author = $book['author'] ?? '匿名作者';
    $coverA = $book['cover_a'] ?? '#355c5d';
    $coverB = $book['cover_b'] ?? '#d6aa67';
@endphp
<div class="book-cover book-cover-{{ $size }}" style="--cover-a: {{ $coverA }}; --cover-b: {{ $coverB }}" aria-label="{{ $title }}，{{ $author }} 著">
    <span class="cover-edge"></span><span class="cover-title">{{ $title }}</span><span class="cover-author">{{ $author }} 著</span><span class="cover-stamp">阅境</span>
</div>