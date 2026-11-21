@if ($paginator->hasPages())
<nav class="filters" aria-label="Страницы задач">
    @if ($paginator->onFirstPage())<span class="muted">Назад</span>@else<a class="button secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Назад</a>@endif
    <span class="muted">Страница {{ $paginator->currentPage() }} из {{ $paginator->lastPage() }} · Всего {{ $paginator->total() }}</span>
    @if ($paginator->hasMorePages())<a class="button secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Далее</a>@else<span class="muted">Далее</span>@endif
</nav>
@endif
