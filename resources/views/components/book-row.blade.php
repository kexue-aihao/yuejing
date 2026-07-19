@props(['novel', 'meta' => null, 'showProgress' => false, 'showDate' => false, 'class' => ''])

<div {{ $attributes->merge(['class' => 'account-book-row' . ($class ? " {$class}" : '')]) }}>
    <a href="{{ route('novels.show', $novel) }}" class="account-book-cover">
        <x-book-cover :book="$novel" :cover-a="$novel->cover_a ?? '#3a6866'" :cover-b="$novel->cover_b ?? '#284c4a'" :title="$novel->title" :author="$novel->author?->name" size="small" />
    </a>
    <div class="account-book-body">
        <h3><a href="{{ route('novels.show', $novel) }}" class="account-book-title">{{ $novel->title }}</a></h3>
        @if($meta)
            <p class="account-book-meta">{{ $meta }}</p>
        @endif
        @if($showProgress && isset($novel->progress))
            <div class="account-book-bar"><span class="account-book-fill" style="width:{{ $novel->progress }}%"></span></div>
        @endif
        @if($showDate && isset($novel->pivot?->created_at))
            <span class="account-book-date">{{ $novel->pivot->created_at->format('Y-m-d') }}</span>
        @endif
    </div>
    @if(isset($actions) && trim((string) $actions) !== '')
        <div class="account-book-actions">{{ $actions }}</div>
    @endif
</div>
