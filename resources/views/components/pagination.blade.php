@props(['paginator'])

@if($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('ui.pagination.navigation') }}">
        <span class="pagination-summary">
            {{ __('ui.pagination.summary', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage(), 'total' => $paginator->total()]) }}
        </span>
        <div class="pagination-links">
            {{-- Previous --}}
            @if($paginator->onFirstPage())
                <span class="pagination-item disabled" aria-disabled="true">‹ {{ __('ui.pagination.previous') }}</span>
            @else
                <a class="pagination-item" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ {{ __('ui.pagination.previous') }}</a>
            @endif

            {{-- Numbers --}}
            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page == $paginator->currentPage())
                    <span class="pagination-item active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="pagination-item" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Next --}}
            @if($paginator->hasMorePages())
                <a class="pagination-item" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('ui.pagination.next') }} ›</a>
            @else
                <span class="pagination-item disabled" aria-disabled="true">{{ __('ui.pagination.next') }} ›</span>
            @endif
        </div>
    </nav>
@endif
