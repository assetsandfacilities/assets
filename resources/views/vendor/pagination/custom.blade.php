@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="app-pagination">
        <div class="app-pagination-info">
            @if ($paginator->firstItem())
                Showing <strong>{{ $paginator->firstItem() }}</strong> to <strong>{{ $paginator->lastItem() }}</strong> of <strong>{{ $paginator->total() }}</strong> results
            @else
                Showing <strong>{{ $paginator->count() }}</strong> {{ Str::plural('result', $paginator->count()) }}
            @endif
        </div>

        <ul class="app-pagination-list">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="app-page-item disabled" aria-disabled="true">
                    <span class="app-page-link">Previous</span>
                </li>
            @else
                <li class="app-page-item">
                    <a class="app-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">Previous</a>
                </li>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="app-page-item disabled"><span class="app-page-link app-page-dots">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="app-page-item active" aria-current="page"><span class="app-page-link">{{ $page }}</span></li>
                        @else
                            <li class="app-page-item"><a class="app-page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="app-page-item">
                    <a class="app-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">Next</a>
                </li>
            @else
                <li class="app-page-item disabled" aria-disabled="true">
                    <span class="app-page-link">Next</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
