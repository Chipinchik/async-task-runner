import Alpine from "alpinejs";
import {
    statuses,
    eventLabels,
    recentTransitions,
    mergeEvents,
    workerTask,
    freshWorkers,
} from "./monitor-state";
const types = {
    demo_delay: "Тестовая нагрузка",
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
    eventLabel(value) {
        return eventLabels[value] || value;
    },
    date(value) {
        return value ? new Date(value).toLocaleString("ru-RU") : "—";
    },
    time(value) {
        return value ? new Date(value).toLocaleTimeString("ru-RU") : "—";
    },
    taskUrl(id) {
        return "/tasks/" + encodeURIComponent(id);
    },
};
async function getJson(url) {
    let response;
    try {
        response = await fetch(url, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            signal: AbortSignal.timeout(8000),
        });
    } catch {
        throw new Error("Нет связи с сервером. Повторяем подключение…");
    }
    if (response.status === 401)
        throw new Error("Сессия завершена. Войдите снова.");
    if (response.status === 403)
        throw new Error("Недостаточно прав для просмотра данных.");
    if (!response.ok)
        throw new Error("Не удалось обновить данные. Повторяем подключение.");
    return response.json();
}
Alpine.data("submitForm", () => ({
    submitting: false,
    init() {
        this.$el.addEventListener("submit", () => {
            this.submitting = true;
        });
        this.restore = () => {
            this.submitting = false;
        };
        window.addEventListener("pageshow", this.restore);
    },
    destroy() {
        window.removeEventListener("pageshow", this.restore);
    },
}));
Alpine.data("taskDetail", (url) => ({
    ...common,
    task: null,
    delivery: null,
    timer: null,
    disposed: false,
    connected: false,
    showProgress: false,
    connection: "Загрузка состояния…",
    async init() {
        await this.poll();
    },
    timeline() {
        return [...(this.task?.events || [])]
            .filter(
                (event) =>
                    this.showProgress || event.event_type !== "task_progress",
            )
            .sort((a, b) => a.id - b.id);
    },
    async poll() {
        try {
            if (!document.hidden) {
                const data = await getJson(url);
                if (this.disposed) return;
                this.task = data.task;
                this.delivery = data.delivery;
                this.connected = true;
                this.connection =
                    "Обновлено " + new Date().toLocaleTimeString("ru-RU");
            }
        } catch (error) {
            this.connected = false;
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
    snapshot: null,
    events: [],
    cursor: 0,
    timer: null,
    flashTimer: null,
    disposed: false,
    flashes: {},
    connected: false,
    eventFilter: "important",
    animate: true,
    lastReceived: 0,
    now: Date.now(),
    connection: "Подключение к мониторингу…",
    async init() {
        this.tick = setInterval(() => {
            this.now = Date.now();
        }, 1000);
        await this.poll();
    },
    fresh() {
        return this.connected && this.now - this.lastReceived < 10000;
    },
    metric(key) {
        return this.fresh() ? (this.snapshot?.broker?.[key] ?? "—") : "—";
    },
    onlineWorkers() {
        return this.fresh()
            ? freshWorkers(this.snapshot, this.lastReceived, this.now)
            : [];
    },
    offlineWorkers() {
        const online = new Set(this.onlineWorkers().map((w) => w.worker_id));
        return (
            this.snapshot?.workers.filter((w) => !online.has(w.worker_id)) || []
        );
    },
    onlineCount() {
        return this.onlineWorkers().length;
    },
    currentTask(workerId) {
        return this.fresh() ? workerTask(this.snapshot, workerId) : null;
    },
    workerFlash(id, key) {
        return (
            this.animate && this.fresh() && !!this.flashes.workers?.[id]?.[key]
        );
    },
    visibleEvents() {
        return this.events
            .filter(
                (event) =>
                    this.eventFilter === "all" ||
                    (this.eventFilter === "errors"
                        ? event.event_type === "task_failed"
                        : event.event_type !== "task_progress"),
            )
            .slice(0, 50);
    },
    toggleAnimation() {
        this.animate = !this.animate;
        if (!this.animate) this.flashes = {};
    },
    async poll() {
        try {
            if (!document.hidden) {
                const data = await getJson(url + "?after=" + this.cursor);
                if (this.disposed) return;
                // The first snapshot is history, not activity happening now.
                const mayAnimate = this.fresh() && this.animate;
                this.flashes = mayAnimate
                    ? recentTransitions(
                          data.events,
                          new Set(this.events.map((e) => e.id)),
                          data.server_time,
                      )
                    : {};
                clearTimeout(this.flashTimer);
                this.flashTimer = setTimeout(() => {
                    this.flashes = {};
                }, 1200);
                this.snapshot = data;
                this.cursor = data.cursor;
                this.connected = true;
                this.lastReceived = Date.now();
                this.now = this.lastReceived;
                this.events = mergeEvents(this.events, data.events);
                this.connection =
                    "Подключено · " + new Date().toLocaleTimeString("ru-RU");
            }
        } catch (error) {
            this.connected = false;
            this.flashes = {};
            this.connection = error.message;
        }
        if (!this.disposed) this.timer = setTimeout(() => this.poll(), 1500);
    },
    destroy() {
        this.disposed = true;
        clearTimeout(this.timer);
        clearTimeout(this.flashTimer);
        clearInterval(this.tick);
    },
}));
window.Alpine = Alpine;
Alpine.start();
