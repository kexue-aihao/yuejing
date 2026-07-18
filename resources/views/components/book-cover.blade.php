@props(['book' => [], 'size' => 'small'])

@php
    $title = $book['title'] ?? __('ui.components.untitled_work');
    $author = $book['author'] ?? __('ui.components.anonymous_author');
    $coverA = $book['cover_a'] ?? '#355c5d';
    $coverB = $book['cover_b'] ?? '#d6aa67';
@endphp
<div class="book-cover book-cover-{{ $size }}" style="--cover-a: {{ $coverA }}; --cover-b: {{ $coverB }}" aria-label="{{ __('ui.components.author_credit', ['author' => $author]) }}: {{ $title }}">
    <span class="cover-edge"></span><span class="cover-title">{{ $title }}</span><span class="cover-author">{{ __('ui.components.author_credit', ['author' => $author]) }}</span><span class="cover-stamp">{{ __('ui.components.cover_brand') }}</span>
</div>
