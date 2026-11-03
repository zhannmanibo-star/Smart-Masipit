<div class="pagination">
    <span class="muted">
        Showing {{ $records->firstItem() ?? 0 }}
        to {{ $records->lastItem() ?? 0 }}
        of {{ $records->total() }}
    </span>

    @if ($records->hasPages())
        <nav class="pagination-links" aria-label="{{ $paginationLabel }}">
            @if ($records->previousPageUrl())
                <a href="{{ $records->previousPageUrl() }}">Previous</a>
            @endif

            <span>
                {{ $records->currentPage() }}
                / {{ $records->lastPage() }}
            </span>

            @if ($records->nextPageUrl())
                <a href="{{ $records->nextPageUrl() }}">Next</a>
            @endif
        </nav>
    @endif
</div>