import Alpine from "alpinejs";
const statuses = {
    queued: "В очереди",
    processing: "В работе",
    completed: "Готово",
    failed: "Ошибка",
};
const types = {
    demo_delay: "Задержка",
    text_analysis: "Анализ текста",
    csv_analysis: "Анализ CSV",
};
const common = {
    statuses,
    statusLabel(value) {
        return statuses[value] || value;
    },
    typeLabel(value) {
        return types[value] || value;
    },
    date(value) {
        return value ? new Date(value).toLocaleString("ru-RU") : "—";
    },
};
async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
        signal: AbortSignal.timeout(8000),
    });
    if (response.status === 401) {
        throw new Error("Сессия завершена. Войдите снова.");
    }
    if (!response.ok) {
        throw new Error(
            "Связь потеряна. Данные могут быть устаревшими; повторяем запрос.",
        );
    }
    return response.json();
}
Alpine.data("taskDetail", (url) => ({
    ...common,
    task: null,
    delivery: null,
    timer: null,
    disposed: false,
    connection: "Загрузка состояния…",
    async init() {
        await this.poll();
    },
    async poll() {
        try {
            if (!document.hidden) {
                const data = await getJson(url);
                if (this.disposed) return;
                this.task = data.task;
                this.delivery = data.delivery;
                this.connection =
                    "Обновлено " + new Date().toLocaleTimeString("ru-RU");
            }
        } catch (error) {
            this.connection = error.message;
        }
        if (!this.disposed) this.timer = setTimeout(() => this.poll(), 1500);
    },
    destroy() {
        this.disposed = true;
        clearTimeout(this.timer);
    },
}));
Alpine.data("monitor", (url) => ({
    ...common,
    showOffline: false,
    snapshot: null,
    events: [],
    cursor: 0,
    timer: null,
    flashTimer: null,
    disposed: false,
    flashes: {},
    connection: "Подключение к мониторингу…",
    async init() {
        await this.poll();
    },
    metric(key) {
        return this.snapshot?.broker?.[key] ?? "нет данных";
    },
    onlineCount() {
        return (
            this.snapshot?.workers.filter((worker) => worker.online).length || 0
        );
    },
    async poll() {
        try {
            if (!document.hidden) {
                const data = await getJson(url + "?after=" + this.cursor);
                if (this.disposed) return;
                if (this.snapshot) {
                    this.flashes = {
                        created: data.events.some(
                            (e) => e.event_type === "task_created",
                        ),
                        queued: data.events.some(
                            (e) => e.event_type === "task_queued",
                        ),
                        started: data.events.some((e) =>
                            ["task_started", "task_progress"].includes(
                                e.event_type,
                            ),
                        ),
                        completed: data.events.some(
                            (e) => e.event_type === "task_completed",
                        ),
                    };
                    clearTimeout(this.flashTimer);
                    this.flashTimer = setTimeout(() => {
                        this.flashes = {};
                    }, 1300);
                }
                this.snapshot = data;
                this.cursor = data.cursor;
                this.events = [
                    ...new Map(
                        [...data.events, ...this.events].map((event) => [
                            event.id,
                            event,
                        ]),
                    ).values(),
                ]
                    .sort((a, b) => b.id - a.id)
                    .slice(0, 100);
                this.connection =
                    "Подключено · обновлено " +
                    new Date().toLocaleTimeString("ru-RU");
            }
        } catch (error) {
            this.connection = error.message;
            this.flashes = {};
            if (this.snapshot)
                this.snapshot.workers.forEach((worker) => {
                    worker.online = false;
                });
        }
        if (!this.disposed) this.timer = setTimeout(() => this.poll(), 1500);
    },
    destroy() {
        this.disposed = true;
        clearTimeout(this.timer);
        clearTimeout(this.flashTimer);
    },
}));
window.Alpine = Alpine;
Alpine.start();
