<x-layout title="Карточка задачи">
    <div x-data="taskDetail(@js(route('api.tasks.show', $task)))">
        <x-page-heading :title="\App\Models\Task::TYPES[$task->type]" :description="'Задача #' . substr($task->id, -8)"><a class="button secondary"
                href="{{ route('tasks.index') }}"><x-icon name="tasks" />К списку задач</a></x-page-heading>
        <x-connection-status />
        <div class="skeleton" x-show="!task">Загружаем состояние задачи…</div>
        <template x-if="task">
            <div>
                <div class="detail-meta">
                    <div><small>Состояние</small><span class="badge" :class="task.status"><span
                                class="status-dot"></span><span x-text="statusLabel(task.status)"></span></span></div>
                    <div><small>Длительность попытки</small><strong
                            x-text="task.duration_ms === null ? 'Ещё не завершена' : (task.duration_ms / 1000).toFixed(3) + ' с'"></strong>
                    </div>
                    <div><small>Попытки в текущем цикле</small><strong
                            x-text="task.attempts + ' / ' + task.max_attempts"></strong></div>
                </div>
                <div class="detail-grid">
                    <div>
                        <section class="panel padded">
                            <div class="panel-heading">
                                <h2>Выполнение</h2><x-icon name="activity" />
                            </div>
                            <div x-show="task.progress !== null" class="progress-block">
                                <div class="progress-track" role="progressbar" :aria-valuenow="task.progress"
                                    aria-valuemin="0" aria-valuemax="100" aria-label="Прогресс задачи">
                                    <div :style="'transform:scaleX(' + task.progress / 100 + ')'"></div>
                                </div><strong x-text="task.progress + '%'"></strong>
                            </div>
                            <p class="notice section-gap" x-show="task.status === 'queued'"
                                x-text="delivery?.published_at ? 'Брокер подтвердил публикацию. Ожидаем свободный Worker.' : (delivery?.last_error || 'Задача сохранена и ожидает отправки в RabbitMQ.')">
                            </p>
                            <dl class="details">
                                <dt>Worker</dt>
                                <dd class="mono" x-text="task.worker_id || 'Ещё не получил задачу'"></dd>
                                <dt>Создана</dt>
                                <dd x-text="date(task.created_at)"></dd>
                                <dt>Опубликована</dt>
                                <dd x-text="date(task.queued_at)"></dd>
                                <dt>Начата</dt>
                                <dd x-text="date(task.started_at)"></dd>
                                <dt>Завершена</dt>
                                <dd x-text="date(task.finished_at)"></dd>
                            </dl>
                            <p class="notice error" x-show="task.error_message" x-text="task.error_message"></p>
                            @can('admin')
                                <form x-show="task.status === 'failed'" method="POST"
                                    action="{{ route('tasks.retry', $task) }}" x-data="submitForm">@csrf<x-submit
                                        label="Повторить задачу" icon="refresh" /></form>
                            @endcan
                        </section>
                        <section class="panel padded section-gap">
                            <h2>Входные данные</h2>
                            <template x-if="task.type === 'demo_delay'">
                                <dl class="details">
                                    <dt>Длительность</dt>
                                    <dd x-text="task.input_data.seconds + ' секунд'"></dd>
                                </dl>
                            </template>
                            <template x-if="task.type === 'text_analysis'">
                                <pre x-text="task.input_data.text"></pre>
                            </template>
                            <template x-if="task.type === 'csv_analysis'">
                                <dl class="details">
                                    <dt>Файл</dt>
                                    <dd x-text="task.input_data.filename || 'CSV-файл'"></dd>
                                    <dt>Разделитель</dt>
                                    <dd
                                        x-text="task.input_data.delimiter === ',' ? 'Запятая' : task.input_data.delimiter === ';' ? 'Точка с запятой' : 'Табуляция'">
                                    </dd>
                                </dl>
                            </template>
                            <details>
                                <summary>Идентификатор и параметры</summary>
                                <p class="mono">{{ $task->id }}</p>
                                <pre x-text="JSON.stringify(task.input_data,null,2)"></pre>
                            </details>
                        </section>
                    </div>
                    <section class="panel padded">
                        <div class="panel-heading">
                            <h2>Результат обработки</h2><x-icon name="file" />
                        </div>
                        <div class="empty-state" x-show="!task.result_data"><span class="icon-tile"><x-icon
                                    name="clock" /></span>
                            <h3 x-text="task.status === 'failed' ? 'Результат не получен' : 'Результат ещё готовится'">
                            </h3>
                            <p
                                x-text="task.status === 'failed' ? 'Проверьте сообщение об ошибке и историю выполнения.' : 'Он появится здесь автоматически после успешного завершения.'">
                            </p>
                        </div>
                        @include('tasks.result')
                    </section>
                </div>
                <section class="panel section-gap">
                    <div class="panel-heading">
                        <div>
                            <h2>История выполнения</h2>
                            <p>События реальной обработки · время вашего браузера</p>
                        </div><label class="timeline-toggle"><input type="checkbox" x-model="showProgress">Показать шаги
                            прогресса</label>
                    </div>
                    <div class="timeline"><template x-for="event in timeline()" :key="event.id">
                            <div class="timeline-row" :class="event.event_type"><span class="timeline-marker">
                                    <x-icon name="alert" x-show="event.event_type === 'task_failed'" />
                                    <x-icon name="check" x-show="event.event_type === 'task_completed'" />
                                    <x-icon name="activity" x-show="!['task_failed','task_completed'].includes(event.event_type)" />
                                </span>
                                <div>
                                    <h3 x-text="eventLabel(event.event_type)"></h3>
                                    <p x-text="event.message"></p>
                                    <p class="mono" x-text="event.worker_id || 'Приложение / диспетчер'"></p>
                                </div><time x-text="date(event.created_at)"></time>
                            </div>
                        </template></div>
                </section>
            </div>
        </template>
    </div>
</x-layout>
