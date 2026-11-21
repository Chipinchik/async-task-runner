<x-layout title="Обзор">
    @php
        $total = $counts->sum();
        $completed = $counts['completed'] ?? 0;
        $completion = $total ? round(($completed / $total) * 100) : 0;
        $offset = 0;
        $segments = [];
        foreach (
            ['completed' => '--success', 'processing' => '--primary', 'queued' => '--warning', 'failed' => '--danger']
            as $status => $color
        ) {
            $end = $offset + ($total ? (($counts[$status] ?? 0) / $total) * 100 : 0);
            $segments[] = "var($color) $offset% $end%";
            $offset = $end;
        }
    @endphp
    <x-page-heading title="Обзор рабочего пространства"
        description="Всё, что происходит с вашими задачами, — на одном экране.">
        <a class="button" href="{{ route('tasks.create') }}"><x-icon name="plus" />Создать задачу</a>
    </x-page-heading>
    <div class="stats">
        <div class="stat">
            <div class="stat-top"><span>Всего задач</span><span class="stat-icon"><x-icon name="tasks" /></span></div>
            <strong>{{ $total }}</strong><small>За всё время</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>Завершено</span><span class="stat-icon green"><x-icon name="check" /></span>
            </div><strong>{{ $completed }}</strong><small><em>{{ $completion }}%</em> от всех задач</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>В обработке</span><span class="stat-icon"><x-icon name="activity" /></span>
            </div><strong>{{ $counts['processing'] ?? 0 }}</strong><small>{{ $counts['queued'] ?? 0 }} ожидают
                запуска</small>
        </div>
        <div class="stat">
            <div class="stat-top"><span>С ошибкой</span><span class="stat-icon red"><x-icon name="alert" /></span>
            </div><strong>{{ $counts['failed'] ?? 0 }}</strong><small>Требуют внимания</small>
        </div>
    </div>
    <div class="overview-grid">
        <section class="panel">
            <div class="panel-heading">
                <h2>Распределение задач</h2><span class="muted">Все задачи</span>
            </div>
            <div class="distribution">
                <div class="donut"
                    style="background: {{ $total ? 'conic-gradient(' . implode(', ', $segments) . ')' : 'var(--border)' }}"
                    aria-hidden="true">
                    <div>
                        <strong>{{ $total ? $completion . '%' : '—' }}</strong><small>{{ $total ? 'завершено' : 'нет задач' }}</small>
                    </div>
                </div>
                <div class="legend">
                    @foreach (['completed', 'processing', 'queued', 'failed'] as $status)
                        <a href="{{ route('tasks.index', ['status' => $status]) }}"><span
                                class="swatch {{ $status }}"></span>{{ \App\Models\Task::STATUSES[$status] }}<strong>{{ $counts[$status] ?? 0 }}</strong><small>{{ $total ? round((($counts[$status] ?? 0) / $total) * 100) : 0 }}%</small></a>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="panel monitor-callout">
            <div class="callout-flow" aria-hidden="true">
                @foreach (['code', 'queue', 'server', 'database'] as $icon)
                    <span class="flow-icon"><x-icon :name="$icon" /></span>
                    @if (!$loop->last)
                        <span class="flow-line"></span>
                    @endif
                @endforeach
            </div>
            <div>
                <h2>Обработка без ожидания</h2>
                <p>Задачи выполняются в фоне. От создания до сохранения результата — каждый этап остаётся в истории.</p>
            </div>
            @can('admin')
                <a class="button secondary small" href="{{ route('monitor') }}"><x-icon name="activity" />Открыть
                мониторинг</a>@else<a class="button secondary small" href="{{ route('tasks.index') }}">Посмотреть мои
                    задачи</a>
            @endcan
        </section>
    </div>
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Последние задачи</h2>
                <p>Статусы и результаты недавних запусков</p>
            </div><a class="text-link" href="{{ route('tasks.index') }}">Все задачи</a>
        </div><x-task-table :tasks="$tasks" />
    </section>
    <div class="type-grid">
        @foreach (['demo_delay' => ['clock', 'Тестовая задержка', 'Наглядный прогресс и проверка параллельной обработки.'], 'text_analysis' => ['text', 'Анализ текста', 'Слова, символы и частоты в русском и английском тексте.'], 'csv_analysis' => ['file', 'Анализ CSV', 'Структура таблицы, пропуски и числовая статистика.']] as $type => $info)
            <a class="type-card" href="{{ route('tasks.create', ['type' => $type]) }}"><span class="icon-tile"><x-icon
                        :name="$info[0]" /></span>
                <h3>{{ $info[1] }}</h3>
                <p>{{ $info[2] }}</p>
            </a>
        @endforeach
    </div>
</x-layout>
