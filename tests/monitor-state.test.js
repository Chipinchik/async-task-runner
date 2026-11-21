import test from "node:test";
import assert from "node:assert/strict";
import {
    transitions,
    workerTask,
    freshWorkers,
} from "../resources/js/monitor-state.js";

test("publication never guesses a worker and duplicate events do not animate", () => {
    const events = [{ id: 1, event_type: "task_queued", worker_id: null }];
    assert.equal(transitions(events, new Set()).published, true);
    assert.deepEqual(transitions(events, new Set()).workers, {});
    assert.equal(transitions(events, new Set([1])).published, false);
});

test("start and save packets follow only the explicitly confirmed worker", () => {
    const result = transitions(
        [
            { id: 2, event_type: "task_started", worker_id: "worker-b" },
            { id: 3, event_type: "task_completed", worker_id: "worker-c" },
            { id: 4, event_type: "task_started", worker_id: null },
        ],
        new Set(),
    );
    assert.equal(result.workers["worker-b"].received, true);
    assert.equal(result.workers["worker-b"].saved, false);
    assert.equal(result.workers["worker-c"].saved, true);
    assert.equal(result.workers["worker-a"], undefined);
    assert.equal(result.workers.null, undefined);
});

test("completed or queued tasks are never presented as running on a worker", () => {
    const snapshot = {
        tasks: [
            { id: "a", status: "completed", worker_id: "worker-a" },
            { id: "b", status: "queued", worker_id: "worker-b" },
            {
                id: "c",
                status: "processing",
                worker_id: "worker-c",
                progress: null,
            },
        ],
    };
    assert.equal(workerTask(snapshot, "worker-a"), null);
    assert.equal(workerTask(snapshot, "worker-b"), null);
    assert.equal(workerTask(snapshot, "worker-c").progress, null);
});

test("heartbeats expire locally using server time despite browser clock skew", () => {
    const snapshot = {
        server_time: "2026-01-01T00:00:10Z",
        heartbeat_ttl: 15,
        workers: [
            {
                worker_id: "b",
                online: true,
                last_seen_at: "2026-01-01T00:00:08Z",
            },
            {
                worker_id: "a",
                online: true,
                last_seen_at: "2026-01-01T00:00:07Z",
            },
            {
                worker_id: "old",
                online: false,
                last_seen_at: "2026-01-01T00:00:00Z",
            },
        ],
    };
    assert.deepEqual(
        freshWorkers(snapshot, 1000, 2000).map((w) => w.worker_id),
        ["a", "b"],
    );
    assert.deepEqual(freshWorkers(snapshot, 1000, 16000), []);
});
