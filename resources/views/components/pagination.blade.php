@props(['paginator'])

@if($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="分页导航">
        <span class="pagination-summary">
            第 {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} 页，共 {{ $paginator->total() }} 项
        </span>
        <div class="pagination-links">
            {{-- Previous --}}
            @if($paginator->onFirstPage())
                <span class="pagination-item disabled" aria-disabled="true">‹ 上一页</span>
            @else
                <a class="pagination-item" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ 上一页</a>
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
                <a class="pagination-item" href="{{ $paginator->nextPageUrl() }}" rel="next">下一页 ›</a>
            @else
                <span class="pagination-item disabled" aria-disabled="true">下一页 ›</span>
            @endif
        </div>
    </nav>
@endif
