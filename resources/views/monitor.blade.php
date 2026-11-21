<x-layout title="Мониторинг">
    <div x-data="monitor(@js(route('api.monitor')))">
        <x-page-heading title="Мониторинг обработки"
            description="От отправки в очередь до готового результата. Все события — в реальном времени.">
            <form method="POST" action="{{ route('admin.demo') }}" x-data="submitForm">@csrf<x-submit
                    label="Запустить нагрузку" icon="play" /></form>
        </x-page-heading>
        <div class="monitor-toolbar">
            <x-connection-status :monitor="true" /><button class="button secondary small" @click="toggleAnimation()"
                :aria-pressed="animate"><x-icon name="activity" /><span
                    x-text="animate ? 'Анимация включена' : 'Анимация выключена'"></span></button>
        </div>
        <div class="notice stale-banner" x-cloak x-show="snapshot && !fresh()">Свежие данные недоступны. Статистика
            задач ниже отражает последнее успешное обновление. Состояние брокера и Workers не подтверждено.</div>
        <div class="skeleton" x-show="!snapshot">Подключаемся к системе…</div>
        <template x-if="snapshot">
            <div>
                <div class="stats monitor-stats">
                    <div class="stat">
                        <div class="stat-top"><span>Ожидают в RabbitMQ</span><span class="stat-icon amber"><x-icon
                                    name="queue" /></span></div><strong
                            x-text="metric('messages_ready')"></strong><small>Сообщения, готовые к доставке</small>
                    </div>
                    <div class="stat">
                        <div class="stat-top"><span>Доставлены Workers</span><span class="stat-icon"><x-icon
                                    name="activity" /></span></div><strong
                            x-text="metric('messages_unacknowledged')"></strong><small>Ожидают подтверждения
                            обработки</small>
                    </div>
                    <div class="stat">
                        <div class="stat-top"><span>Активные Workers</span><span class="stat-icon"><x-icon
                                    name="server" /></span></div><strong
                            x-text="fresh() ? onlineCount() : '—'"></strong><small
                            x-text="'По heartbeat · TTL ' + snapshot.heartbeat_ttl + ' с'"></small>
                    </div>
                    <div class="stat">
                        <div class="stat-top"><span>Результаты сохранены</span><span class="stat-icon green"><x-icon
                                    name="database" /></span></div><strong
                            x-text="snapshot.counts.completed || 0"></strong><small>Завершённые задачи в
                            PostgreSQL</small>
                    </div>
                </div>
                <section class="panel diagram-panel">
                    <div class="panel-heading diagram-heading">
                        <div>
                            <h2>Путь задачи <span class="badge neutral">Схема обработки</span></h2>
                            <p>Laravel → RabbitMQ → Workers → PostgreSQL</p>
                        </div><span class="muted">Нагрузка: 6 задач × 10 секунд</span>
                    </div>
                    <div class="pipeline" aria-label="Схема распределённой обработки">
                        <div class="flow-node node-laravel" :class="{ 'node-flash': animate && flashes.created }">
                            <div class="node-head"><span class="icon-tile"><x-icon name="code" /></span>
                                <div>
                                    <h3>Laravel</h3><small>ПРИЛОЖЕНИЕ</small>
                                </div>
                            </div>
                            <div class="node-row"><span>В outbox</span><strong
                                    x-text="snapshot.outbox_pending"></strong></div>
                            <div class="node-row"><span>Ошибки отправки</span><strong
                                    x-text="snapshot.outbox_errors"></strong></div>
                            <div class="node-foot">Сохраняет и публикует задачи</div>
                        </div>
                        <div class="flow-node node-rabbit" :class="{ 'node-flash': animate && flashes.published }">
                            <template x-if="animate && flashes.published"><i class="flow-packet publish"
                                    aria-hidden="true"></i></template>
                            <div class="node-head"><span class="icon-tile"><x-icon name="queue" /></span>
                                <div>
                                    <h3>RabbitMQ</h3><small>БРОКЕР СООБЩЕНИЙ</small>
                                </div>
                            </div>
                            <div class="node-row"><span>В очереди</span><strong
                                    x-text="metric('messages_ready')"></strong></div>
                            <div class="node-row"><span>Потребители</span><strong x-text="metric('consumers')"></strong>
                            </div>
                            <div class="node-foot"
                                x-text="!fresh() ? 'Нет свежих данных' : snapshot.broker.available ? (snapshot.broker.messages_ready === 0 ? 'Очередь пуста' : 'Сообщения ожидают доставки') : 'Брокер недоступен · нет метрик'">
                            </div>
                        </div>
                        <div class="worker-lanes">
                            <template x-for="(worker,index) in onlineWorkers()" :key="worker.worker_id">
                                <div class="flow-node worker-node"
                                    :class="{ busy: currentTask(worker.worker_id), 'node-flash': workerFlash(worker.worker_id,
                                            'progress') || workerFlash(worker.worker_id, 'received') }">
                                    <template x-if="workerFlash(worker.worker_id,'received')"><i class="flow-packet"
                                            aria-hidden="true"></i></template><template
                                        x-if="workerFlash(worker.worker_id,'saved')"><i class="flow-packet outbound"
                                            aria-hidden="true"></i></template>
                                    <div class="node-head"><span class="icon-tile"><x-icon name="server" /></span>
                                        <h3 x-text="'Worker ' + (index + 1)"></h3><span class="badge"
                                            :class="currentTask(worker.worker_id) ? 'processing' : 'completed'"
                                            x-text="currentTask(worker.worker_id) ? 'В работе' : 'Свободен'"></span>
                                    </div>
                                    <p class="worker-id" x-text="worker.worker_id"></p>
                                    <template x-if="currentTask(worker.worker_id)">
                                        <div><a class="worker-task" :href="taskUrl(currentTask(worker.worker_id).id)"
                                                x-text="typeLabel(currentTask(worker.worker_id).type) + ' · #' + currentTask(worker.worker_id).id.slice(-8)"></a>
                                            <template x-if="currentTask(worker.worker_id).progress !== null">
                                                <div class="progress-inline">
                                                    <div class="progress-track" role="progressbar"
                                                        aria-label="Прогресс Worker" aria-valuemin="0"
                                                        aria-valuemax="100"
                                                        :aria-valuenow="currentTask(worker.worker_id).progress">
                                                        <div
                                                            :style="'transform:scaleX(' + currentTask(worker.worker_id)
                                                                .progress / 100 + ')'">
                                                        </div>
                                                    </div><span
                                                        x-text="currentTask(worker.worker_id).progress + '%'"></span>
                                                </div>
                                            </template>
                                            <p class="worker-empty"
                                                x-show="currentTask(worker.worker_id)?.progress === null">Выполняет
                                                обработку</p>
                                        </div>
                                    </template>
                                    <p class="worker-empty" x-show="!currentTask(worker.worker_id)">Ожидает следующую
                                        задачу</p>
                                </div>
                            </template>
                            <div class="flow-node empty-state compact" x-show="onlineCount() === 0"><x-icon
                                    name="server" />
                                <p x-text="fresh() ? 'Нет активных Workers' : 'Состояние Workers неизвестно'"></p>
                                <small class="help">Ожидаем свежий heartbeat</small>
                            </div>
                        </div>
                        <div class="flow-node node-postgres" :class="{ 'node-flash': animate && flashes.completed }">
                            <div class="node-head"><span class="icon-tile"><x-icon name="database" /></span>
                                <div>
                                    <h3>PostgreSQL</h3><small>РЕЗУЛЬТАТЫ</small>
                                </div>
                            </div>
                            <div class="node-row"><span>Завершено</span><strong
                                    x-text="snapshot.counts.completed || 0"></strong></div>
                            <div class="node-row"><span>С ошибкой</span><strong
                                    x-text="snapshot.counts.failed || 0"></strong></div>
                            <div class="node-foot">Состояния, результаты и история</div>
                        </div>
                    </div>
                    <div class="diagram-footer">
                        <div class="diagram-legend"><span><i></i>Получение задания</span><span><i
                                    class="green"></i>Сохранение результата</span></div><span>Анимация только по новым
                            событиям</span>
                    </div>
                    <p class="help">Метрики RabbitMQ обновляются с задержкой в несколько секунд. Прогресс задач поступает отдельно из журнала выполнения.</p>
                    <details class="offline-list" x-show="offlineWorkers().length">
                        <summary x-text="'Неактивные / неподтверждённые Workers (' + offlineWorkers().length + ')'">
                        </summary><template x-for="worker in offlineWorkers()" :key="worker.worker_id">
                            <div class="offline-worker"><span class="mono" x-text="worker.worker_id"></span>
                                <p x-text="'Последний heartbeat: ' + date(worker.last_seen_at)"></p>
                            </div>
                        </template>
                    </details>
                </section>
                <div class="monitor-bottom">
                    <section class="panel">
                        <div class="panel-heading">
                            <div>
                                <h2>Текущие задачи</h2>
                                <p>Статусы из PostgreSQL, до 100 последних задач</p>
                            </div><span class="badge neutral"
                                x-text="(snapshot.counts.queued || 0) + (snapshot.counts.processing || 0)"></span>
                        </div>
                        <template x-for="task in snapshot.tasks" :key="task.id">
                            <div class="active-task">
                                <div class="active-task-top"><a :href="taskUrl(task.id)"
                                        x-text="typeLabel(task.type)"></a><span class="badge" :class="task.status"
                                        x-text="statusLabel(task.status)"></span></div><span class="mono"
                                    x-text="'#' + task.id.slice(-8) + ' · ' + (task.worker_id || (task.queued_at ? 'Опубликована в RabbitMQ' : 'Ожидает публикации'))"></span>
                                <div class="progress-inline" x-show="task.progress !== null">
                                    <div class="progress-track">
                                        <div :style="'transform:scaleX(' + task.progress / 100 + ')'"></div>
                                    </div><span x-text="task.progress + '%'"></span>
                                </div>
                            </div>
                        </template>
                        <div class="empty-state" x-show="snapshot.tasks.length === 0"><span class="icon-tile"><x-icon
                                    name="check" /></span>
                            <h3>Всё обработано</h3>
                            <p>Создайте задачу или запустите тестовую нагрузку.</p>
                        </div>
                    </section>
                    <x-live-events />
                </div>
            </div>
        </template>
    </div>
</x-layout>
