<div class="table-scroll"><table><thead><tr><th>Задача</th><th>Статус</th><th>Прогресс</th><th>Worker</th><th>Создана</th></tr></thead><tbody>
@forelse($tasks as $task)
<tr><td><a class="task-link" href="{{ route('tasks.show', $task) }}">{{ \App\Models\Task::TYPES[$task->type] }}</a><small class="mono">{{ substr($task->id, -8) }}</small></td><td><span class="badge {{ $task->status }}">{{ \App\Models\Task::STATUSES[$task->status] }}</span></td><td>{{ $task->progress !== null ? $task->progress.'%' : '—' }}</td><td class="mono worker-cell" title="{{ $task->worker_id }}">{{ $task->worker_id ?? 'Не назначен' }}</td><td>{{ $task->created_at->format('d.m.Y H:i') }} UTC</td></tr>
@empty<tr><td colspan="5" class="empty">Задач пока нет. Создайте первую — её путь появится в истории событий.</td></tr>@endforelse
</tbody></table></div>
