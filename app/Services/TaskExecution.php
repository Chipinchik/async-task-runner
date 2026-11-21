<?php

namespace App\Services;

use App\Exceptions\TaskProcessingException;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TaskExecution
{
    public function __construct(private WorkerIdentity $worker, private TaskProcessor $processor) {}

    public function run(string $id, int $generation): void
    {
        $token = (string) Str::uuid();
        $task = DB::transaction(function () use ($id, $generation, $token): ?Task {
            $task = Task::lockForUpdate()->find($id);
            if (! $task || $task->generation !== $generation || $task->status !== 'queued') {
                return null;
            }
            $task->update(['status' => 'processing', 'worker_id' => $this->worker->id(), 'execution_token' => $token, 'attempts' => $task->attempts + 1, 'started_at' => now(), 'finished_at' => null, 'progress' => $task->type === 'demo_delay' ? 0 : null, 'lease_expires_at' => now()->addSeconds(config('taskflow.lease_seconds')), 'error_message' => null]);
            $task->record('task_started', 'Worker получил сообщение и начал обработку.', ['attempt' => $task->attempts, 'generation' => $generation]);

            return $task;
        });
        if (! $task) {
            return;
        }
        $this->worker->beat($id, true);
        $started = hrtime(true);
        try {
            $result = $this->processor->process($task, function (?int $percent, string $message) use ($id, $token): void {
                DB::transaction(function () use ($id, $token, $percent, $message): void {
                    $task = Task::lockForUpdate()->findOrFail($id);
                    if ($task->execution_token !== $token || $task->status !== 'processing') {
                        throw new TaskProcessingException('Право выполнения отозвано.');
                    }
                    $task->update(['progress' => $percent, 'lease_expires_at' => now()->addSeconds(config('taskflow.lease_seconds'))]);
                    $task->record('task_progress', $message, ['progress' => $percent]);
                });
                $this->worker->beat($id);
            });
            DB::transaction(function () use ($id, $token, $result, $started): void {
                $task = Task::lockForUpdate()->findOrFail($id);
                if ($task->execution_token !== $token || $task->status !== 'processing') {
                    return;
                }
                $task->update(['status' => 'completed', 'result_data' => $result, 'progress' => $task->type === 'demo_delay' ? 100 : null, 'finished_at' => now(), 'duration_ms' => (int) ((hrtime(true) - $started) / 1e6), 'execution_token' => null, 'lease_expires_at' => null]);
                $task->record('task_completed', 'Результат сохранён в PostgreSQL.', ['duration_ms' => $task->duration_ms]);
            });
        } catch (Throwable $exception) {
            Log::warning('Task execution failed', ['task_id' => $id, 'exception' => $exception->getMessage()]);
            $this->fail($id, $token, $exception instanceof TaskProcessingException ? $exception->getMessage() : 'Ошибка обработки. Подробности в журнале сервера.');
        } finally {
            $this->worker->beat(null, true);
        }
    }

    public function fail(string $id, string $token, string $message, bool $onlyIfExpired = false): void
    {
        DB::transaction(function () use ($id, $token, $message, $onlyIfExpired): void {
            $task = Task::lockForUpdate()->find($id);
            if (! $task || $task->status !== 'processing' || $task->execution_token !== $token) {
                return;
            }
            if ($onlyIfExpired && $task->lease_expires_at?->isFuture()) {
                return;
            }
            $task->update(['status' => 'failed', 'error_message' => mb_substr($message, 0, 1000), 'finished_at' => now(), 'duration_ms' => max(0, (int) $task->started_at->diffInMilliseconds(now())), 'execution_token' => null, 'lease_expires_at' => null]);
            $task->record('task_failed', $task->error_message, ['attempt' => $task->attempts, 'final' => $task->attempts >= $task->max_attempts]);
            if ($task->attempts < $task->max_attempts) {
                $delay = config('taskflow.retry_delays')[min($task->attempts - 1, 2)];
                $task->update(['status' => 'queued', 'generation' => $task->generation + 1, 'progress' => null, 'queued_at' => null]);
                $task->record('task_retried', "Автоматический повтор запланирован через {$delay} с.", ['delay_seconds' => $delay, 'generation' => $task->generation]);
                $task->update(['worker_id' => null]);
                $task->outbox()->create(['generation' => $task->generation, 'available_at' => now()->addSeconds($delay)]);
            }
        });
    }

    public function recoverExpired(): int
    {
        $tasks = Task::where('status', 'processing')->where('lease_expires_at', '<', now())->limit(100)->get();
        foreach ($tasks as $task) {
            $this->fail($task->id, $task->execution_token, 'Worker не продлил аренду выполнения: аварийное завершение или таймаут.', true);
        }

        return $tasks->count();
    }
}
