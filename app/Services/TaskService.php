<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function create(User $user, string $type, array $input): Task
    {
        return DB::transaction(function () use ($user, $type, $input): Task {
            $task = Task::create(['user_id' => $user->id, 'type' => $type, 'status' => 'queued', 'input_data' => $input]);
            $task->record('task_created', 'Задание сохранено; ожидает публикации в RabbitMQ.');
            $task->outbox()->create(['generation' => 1, 'available_at' => now()]);

            return $task;
        });
    }

    public function retry(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            if ($task->status !== 'failed') {
                throw ValidationException::withMessages(['task' => 'Повтор разрешён только для завершившейся ошибкой задачи.']);
            }
            $task->update(['status' => 'queued', 'attempts' => 0, 'generation' => $task->generation + 1, 'execution_token' => null, 'lease_expires_at' => null, 'worker_id' => null, 'progress' => null, 'result_data' => null, 'error_message' => null, 'queued_at' => null, 'started_at' => null, 'finished_at' => null, 'duration_ms' => null]);
            $task->record('task_retried', 'Администратор запросил новый цикл обработки.', ['generation' => $task->generation]);
            $task->outbox()->create(['generation' => $task->generation, 'available_at' => now()]);
        });
    }
}
