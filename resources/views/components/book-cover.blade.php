@props(['book' => [], 'size' => 'small', 'coverA' => null, 'coverB' => null, 'title' => null, 'author' => null])

@php
    $title = $title ?? data_get($book, 'title', __('ui.components.untitled_work'));
    $author = $author ?? data_get($book, 'author', __('ui.components.anonymous_author'));
    $coverUrl = data_get($book, 'cover_url');
    $coverA = $coverA ?? data_get($book, 'cover_a', '#355c5d');
    $coverB = $coverB ?? data_get($book, 'cover_b', '#d6aa67');
@endphp
<div class="book-cover book-cover-{{ $size }}{{ $coverUrl ? ' has-image' : '' }}" style="--cover-a: {{ $coverA }}; --cover-b: {{ $coverB }}" aria-label="{{ __('ui.components.author_credit', ['author' => $author]) }}: {{ $title }}">
    @if ($coverUrl)
        <img class="book-cover-image" src="{{ $coverUrl }}" alt="{{ $title }}" loading="lazy">
        <span class="cover-edge"></span>
    @else
        <span class="cover-edge"></span><span class="cover-title">{{ $title }}</span><span class="cover-author">{{ __('ui.components.author_credit', ['author' => $author]) }}</span><span class="cover-stamp">{{ __('ui.components.cover_brand') }}</span>
    @endif
</div>
