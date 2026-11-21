export const statuses = {
    queued: "В очереди",
    processing: "В работе",
    completed: "Готово",
    failed: "Ошибка",
};
export const eventLabels = {
    task_created: "Задача создана",
    task_queued: "Передана в RabbitMQ",
    task_started: "Worker начал обработку",
    task_progress: "Прогресс обновлён",
    task_completed: "Результат сохранён",
    task_failed: "Ошибка обработки",
    task_retried: "Повтор запланирован",
};

// Only new persisted events may produce a visual transition. Worker routes
// require an explicit worker_id on the event, never a guessed idle worker.
export function transitions(events, seenIds) {
    const fresh = events.filter((event) => !seenIds.has(event.id));
    return {
        created: fresh.some((event) => event.event_type === "task_created"),
        published: fresh.some((event) => event.event_type === "task_queued"),
        completed: fresh.some((event) => event.event_type === "task_completed"),
        workers: Object.fromEntries(
            [
                ...new Set(
                    fresh.map((event) => event.worker_id).filter(Boolean),
                ),
            ].map((id) => [
                id,
                {
                    received: fresh.some(
                        (event) =>
                            event.worker_id === id &&
                            event.event_type === "task_started",
                    ),
                    progress: fresh.some(
                        (event) =>
                            event.worker_id === id &&
                            event.event_type === "task_progress",
                    ),
                    saved: fresh.some(
                        (event) =>
                            event.worker_id === id &&
                            event.event_type === "task_completed",
                    ),
                },
            ]),
        ),
    };
}
export function workerTask(snapshot, workerId) {
    return (
        snapshot?.tasks.find(
            (task) =>
                task.worker_id === workerId && task.status === "processing",
        ) ?? null
    );
}
export function freshWorkers(snapshot, lastReceived, now = Date.now()) {
    if (!snapshot || !lastReceived) return [];
    const elapsed = now - lastReceived;
    return snapshot.workers
        .filter(
            (worker) =>
                worker.online &&
                new Date(snapshot.server_time).getTime() +
                    elapsed -
                    new Date(worker.last_seen_at).getTime() <
                    snapshot.heartbeat_ttl * 1000,
        )
        .sort((a, b) => a.worker_id.localeCompare(b.worker_id));
}
