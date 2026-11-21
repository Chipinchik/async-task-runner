<?php

namespace App\Services;

use App\Jobs\ProcessTask;
use App\Models\Task;
use App\Models\TaskOutbox;
use App\Queue\ConfirmedRabbitMQQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

class OutboxPublisher
{
    public function publish(int $limit = 50): int
    {
        $count = 0;
        $ids = TaskOutbox::whereNull('published_at')->where('available_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            try {
                $sent = DB::transaction(function () use ($id): bool {
                    $entry = TaskOutbox::lockForUpdate()->find($id);
                    if (! $entry || $entry->published_at || $entry->available_at->isFuture()) {
                        return false;
                    }
                    $task = Task::lockForUpdate()->findOrFail($entry->task_id);
                    if ($task->generation !== $entry->generation || $task->status !== 'queued') {
                        $entry->update(['published_at' => now()]);

                        return false;
                    }
                    // The task lock prevents a consumer starting before publication is recorded.
                    Queue::connection('rabbitmq')->push(new ProcessTask($task->id, $task->generation));
                    $entry->update(['published_at' => now(), 'last_error' => null, 'publish_attempts' => $entry->publish_attempts + 1]);
                    $task->update(['queued_at' => now()]);
                    $task->record('task_queued', 'RabbitMQ подтвердил приём сообщения.', ['generation' => $task->generation]);

                    return true;
                });
                $count += (int) $sent;
            } catch (Throwable $exception) {
                TaskOutbox::whereKey($id)->whereNull('published_at')->update(['last_error' => 'Брокер недоступен или не подтвердил публикацию. Автоматический повтор через 5 с.', 'publish_attempts' => DB::raw('publish_attempts + 1'), 'available_at' => now()->addSeconds(5)]);
                Log::warning('Outbox publication failed', ['outbox_id' => $id, 'exception' => $exception->getMessage()]);
                if (Queue::connected('rabbitmq')) {
                    Queue::connection('rabbitmq')->resetAfterFailure();
                }
                break;
            }
        }

        if ($ids->isNotEmpty() && Queue::connected('rabbitmq')) {
            $connection = Queue::connection('rabbitmq');
            if ($connection instanceof ConfirmedRabbitMQQueue) {
                try {
                    $connection->close();
                } catch (Throwable) { /* Reconnect on the next batch. */
                }
                $connection->resetAfterFailure();
            }
        }

        return $count;
    }
}
