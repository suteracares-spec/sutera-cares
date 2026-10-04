{{-- Plain previous/next paging in the portal's own style (the framework default assumes Tailwind). --}}
@if ($paginator->hasPages())
  <nav class="pager" aria-label="Pages">
    @if ($paginator->onFirstPage())
      <span class="btn btn-quiet" aria-disabled="true">&larr; Previous</span>
    @else
      <a class="btn btn-quiet" href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; Previous</a>
    @endif

    <span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

    @if ($paginator->hasMorePages())
      <a class="btn btn-quiet" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &rarr;</a>
    @else
      <span class="btn btn-quiet" aria-disabled="true">Next &rarr;</span>
    @endif
  </nav>
@endif
