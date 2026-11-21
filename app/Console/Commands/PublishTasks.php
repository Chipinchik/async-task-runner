<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisher;
use App\Services\TaskExecution;
use Illuminate\Console\Command;

class PublishTasks extends Command
{
    protected $signature = 'taskflow:publish {--once : Один проход вместо демона}';

    protected $description = 'Публикация outbox в RabbitMQ и восстановление просроченных задач';

    public function handle(OutboxPublisher $publisher, TaskExecution $execution): int
    {
        do {
            $execution->recoverExpired();
            $count = $publisher->publish();
            if ($count > 0) {
                $this->info("Опубликовано: {$count}");
            }
            if (! $this->option('once')) {
                sleep(1);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
