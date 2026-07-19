@props(['book' => [], 'size' => 'small', 'coverA' => null, 'coverB' => null, 'title' => null, 'author' => null])

@php
    $title = $title ?? data_get($book, 'title', __('ui.components.untitled_work'));
    $author = $author ?? data_get($book, 'author', __('ui.components.anonymous_author'));
    $coverUrl = data_get($book, 'cover_url');
    $coverPath = parse_url((string) $coverUrl, PHP_URL_PATH);

    // Local cover URLs must point to a file that the configured public disk can
    // actually read. This prevents stale records or a missing storage link from
    // becoming broken images in every book card.
    if (is_string($coverPath)) {
        $coverPath = ltrim($coverPath, '/');
        if (str_starts_with($coverPath, 'storage/covers/')) {
            $relativeCoverPath = substr($coverPath, strlen('storage/'));
            if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($relativeCoverPath)) {
                $coverUrl = null;
            } else {
                $coverUrl = url('/'.$coverPath);
            }
        }
    }
    $coverA = $coverA ?? data_get($book, 'cover_a', '#355c5d');
    $coverB = $coverB ?? data_get($book, 'cover_b', '#d6aa67');
@endphp
<div class="book-cover book-cover-{{ $size }}{{ $coverUrl ? ' has-image' : '' }}" style="--cover-a: {{ $coverA }}; --cover-b: {{ $coverB }}" aria-label="{{ __('ui.components.author_credit', ['author' => $author]) }}: {{ $title }}">
    @if ($coverUrl)
        <img class="book-cover-image" src="{{ $coverUrl }}" alt="{{ $title }}" loading="lazy" onload="this.parentElement.querySelectorAll('[data-cover-fallback]').forEach((element) => element.style.display = 'none')" onerror="const cover = this.parentElement; this.remove(); cover.classList.remove('has-image'); cover.querySelectorAll('[data-cover-fallback]').forEach((element) => element.style.display = '')">
    @endif
    <span class="cover-edge"></span><span data-cover-fallback class="cover-title" style="{{ $coverUrl ? 'display:none' : '' }}">{{ $title }}</span><span data-cover-fallback class="cover-author" style="{{ $coverUrl ? 'display:none' : '' }}">{{ __('ui.components.author_credit', ['author' => $author]) }}</span><span data-cover-fallback class="cover-stamp" style="{{ $coverUrl ? 'display:none' : '' }}">{{ __('ui.components.cover_brand') }}</span>
</div>
