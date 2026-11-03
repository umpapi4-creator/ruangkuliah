@if($paginator->hasPages())
<div class="pagination">
    <span>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }} · {{ $paginator->total() }} data</span>
    <div class="pagination-actions">
        <a class="btn btn-light btn-sm {{ $paginator->onFirstPage() ? 'disabled' : '' }}" href="{{ $paginator->previousPageUrl() ?: '#' }}">← Sebelumnya</a>
        <a class="btn btn-light btn-sm {{ $paginator->hasMorePages() ? '' : 'disabled' }}" href="{{ $paginator->nextPageUrl() ?: '#' }}">Berikutnya →</a>
    </div>
</div>
@endif
