<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskOutbox;
use App\Models\WorkerHeartbeat;
use App\Services\BrokerMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MonitoringController extends Controller
{
    public function task(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);
        $task->load(['events' => fn ($query) => $query->latest('id')->limit(100)]);

        return response()->json(['task' => $task, 'delivery' => $task->outbox()->where('generation', $task->generation)->first(['published_at', 'available_at', 'last_error'])])->header('Cache-Control', 'no-store');
    }

    public function snapshot(Request $request, BrokerMetrics $metrics): JsonResponse
    {
        Gate::authorize('admin');
        $cursor = max(0, (int) $request->query('after', 0));
        $events = TaskEvent::when($cursor > 0, fn ($query) => $query->where('id', '>', $cursor))->orderBy('id', $cursor ? 'asc' : 'desc')->limit(100)->get();
        $workers = WorkerHeartbeat::where('last_seen_at', '>=', now()->subDay())->orderByDesc('last_seen_at')->limit(100)->get()->map(function ($worker): array {
            return [...$worker->toArray(), 'online' => $worker->last_seen_at->greaterThan(now()->subSeconds(config('taskflow.heartbeat_ttl')))];
        });

        return response()->json([
            'server_time' => now()->toIso8601String(), 'heartbeat_ttl' => config('taskflow.heartbeat_ttl'),
            'counts' => Task::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'outbox_pending' => TaskOutbox::whereNull('published_at')->count(),
            'outbox_errors' => TaskOutbox::whereNull('published_at')->whereNotNull('last_error')->count(),
            'broker' => $metrics->read(), 'workers' => $workers,
            'tasks' => Task::whereIn('status', ['queued', 'processing'])->orderByRaw("CASE WHEN status = 'processing' THEN 0 ELSE 1 END")->latest()->limit(100)->get(['id', 'type', 'status', 'progress', 'worker_id', 'attempts', 'queued_at', 'started_at']),
            'failed_tasks' => Task::where('status', 'failed')->latest('finished_at')->limit(8)->get(['id', 'type', 'error_message', 'attempts', 'max_attempts', 'finished_at']),
            'events' => $events->sortByDesc('id')->values(), 'cursor' => max($cursor, $events->max('id') ?? 0),
        ])->header('Cache-Control', 'no-store');
    }
}
