@if ($tasks->isEmpty())
    <div class="empty-state">
        <span class="icon-tile"><x-icon name="tasks" /></span>
        <h3>{{ request()->hasAny(['status', 'type']) ? 'Задачи не найдены' : 'Здесь появятся ваши задачи' }}</h3>
        <p>{{ request()->hasAny(['status', 'type']) ? 'Попробуйте изменить фильтры или сбросить их.' : 'Создайте первую задачу — мы покажем её прогресс и готовый результат.' }}
        </p><a class="button secondary small"
            href="{{ request()->hasAny(['status', 'type']) ? route('tasks.index') : route('tasks.create') }}">{{ request()->hasAny(['status', 'type']) ? 'Сбросить фильтры' : 'Создать задачу' }}</a>
    </div>
@else
    <div class="table-scroll">
        <table class="task-table">
            <caption class="sr-only">Задачи и состояние их обработки</caption>
            <thead>
                <tr>
                    <th scope="col">Задача</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Прогресс</th>
                    <th scope="col">Worker</th>
                    <th scope="col">Создана</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tasks as $task)
                    <tr>
                        <td>
                            <div class="task-cell"><span class="icon-tile"><x-icon :name="match ($task->type) {
                                'demo_delay' => 'clock',
                                'text_analysis' => 'text',
                                default => 'file',
                            }" /></span>
                                <div><a class="task-link"
                                        href="{{ route('tasks.show', $task) }}">{{ \App\Models\Task::TYPES[$task->type] }}</a><small
                                        class="mono">#{{ substr($task->id, -8) }}</small></div>
                            </div>
                        </td>
                        <td><x-status :status="$task->status" /></td>
                        <td>
                            <div class="progress-inline">
                                @if ($task->progress !== null)
                                    <div class="progress-track" aria-hidden="true">
                                        <div style="transform:scaleX({{ $task->progress / 100 }})"></div>
                                    </div>
                                <span>{{ $task->progress }}%</span>@else<span>{{ $task->status === 'completed' ? 'Завершено' : '—' }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="mono worker-cell">{{ $task->worker_id ?? 'Ожидание Worker' }}</td>
                        <td class="date-cell">
                            {{ $task->created_at->format('d.m.Y') }}<small>{{ $task->created_at->format('H:i') }}
                                UTC</small></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
