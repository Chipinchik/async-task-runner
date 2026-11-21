<x-layout title="Администрирование">
    <div x-data="monitor(@js(route('api.monitor')))">
        <x-page-heading title="Управление системой"
            description="Состояние сервисов, ошибки обработки и контроль повторных запусков."><a class="button secondary"
                href="{{ route('monitor') }}"><x-icon name="activity" />Мониторинг</a></x-page-heading>
        <x-connection-status :monitor="true" />
        <div class="skeleton" x-show="!snapshot">Загружаем состояние системы…</div>
        <template x-if="snapshot">
            <div>
                <div class="system-grid">
                    <section class="panel system-card">
                        <div class="node-head"><span class="icon-tile"><x-icon name="queue" /></span>
                            <h2>RabbitMQ</h2>
                        </div><span class="badge"
                            :class="fresh() && snapshot.broker.available ? 'completed' : 'failed'"
                            x-text="!fresh() ? 'Нет свежих данных' : snapshot.broker.available ? 'Подключён' : 'Недоступен'"></span>
                        <p
                            x-text="'Потребителей: ' + metric('consumers') + ' · В очереди: ' + metric('messages_ready')">
                        </p>
                    </section>
                    <section class="panel system-card">
                        <div class="node-head"><span class="icon-tile"><x-icon name="server" /></span>
                            <h2>Workers</h2>
                        </div><span class="badge" :class="onlineCount() ? 'completed' : 'queued'"
                            x-text="fresh() ? onlineCount() + ' активных' : 'Нет свежих данных'"></span>
                        <p x-text="'Heartbeat · срок актуальности ' + snapshot.heartbeat_ttl + ' с'"></p>
                    </section>
                    <section class="panel system-card">
                        <div class="node-head"><span class="icon-tile"><x-icon name="database" /></span>
                            <h2>PostgreSQL</h2>
                        </div><span class="badge" :class="fresh() ? 'completed' : 'queued'"
                            x-text="fresh() ? 'Отвечает' : 'Нет свежих данных'"></span>
                        <p
                            x-text="'Outbox: ' + snapshot.outbox_pending + ' · Ошибок отправки: ' + snapshot.outbox_errors">
                        </p>
                    </section>
                </div>
                <section class="panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Задачи, требующие внимания</h2>
                            <p>Последние ошибки после завершения автоматических попыток</p>
                        </div><a class="text-link" href="{{ route('tasks.index', ['status' => 'failed']) }}">Все ошибки</a>
                    </div>
                    <template x-for="task in snapshot.failed_tasks" :key="task.id">
                        <div class="active-task">
                            <div class="active-task-top"><a :href="taskUrl(task.id)"
                                    x-text="typeLabel(task.type) + ' · #' + task.id.slice(-8)"></a><span
                                    class="badge failed">Ошибка</span></div>
                            <p class="help" x-text="task.error_message"></p>
                            <p class="help" x-text="'Попыток: ' + task.attempts + ' / ' + task.max_attempts"></p><a
                                class="text-link" :href="taskUrl(task.id)">Детали и повторный запуск</a>
                        </div>
                    </template>
                    <div class="empty-state" x-show="!snapshot.failed_tasks.length"><span class="icon-tile"><x-icon
                                name="check" /></span>
                        <h3>Ошибок нет</h3>
                        <p>Задачи с окончательной ошибкой появятся здесь вместе с причиной сбоя.</p>
                    </div>
                </section>
                <div class="admin-actions">
                    <section class="panel padded"><span class="icon-tile"><x-icon name="play" /></span>
                        <h2>Проверка производительности</h2>
                        <p>6 задач по 10 секунд. Время обработки зависит от количества доступных Workers. Следите за
                            результатом в мониторинге.</p>
                        <form method="POST" action="{{ route('admin.demo') }}" x-data="submitForm">@csrf<x-submit
                                label="Создать 6 тестовых задач" icon="play" /></form>
                    </section>
                    <section class="panel padded"><span class="icon-tile"><x-icon name="refresh" /></span>
                        <h2>Восстановление после ошибки</h2>
                        <p>Система выполняет до трёх попыток с задержками 5 и 15 секунд. Для окончательно неудачной
                            задачи новый цикл запускается из её карточки.</p><a class="button secondary"
                            href="{{ route('tasks.index', ['status' => 'failed']) }}">Перейти к ошибкам</a>
                    </section>
                </div>
            </div>
        </template>
    </div>
</x-layout>
