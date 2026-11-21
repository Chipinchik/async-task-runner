@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Страницы задач">
        @if ($paginator->onFirstPage())
        <span class="button secondary small disabled" aria-disabled="true">Назад</span>@else<a
                class="button secondary small" href="{{ $paginator->previousPageUrl() }}" rel="prev">Назад</a>
        @endif
        <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
        <a class="button secondary small" href="{{ $paginator->nextPageUrl() }}" rel="next">Далее</a>@else<span
                class="button secondary small disabled" aria-disabled="true">Далее</span>
        @endif
    </nav>
@endif
