<?php

namespace App\Queue;

use App\Services\WorkerIdentity;
use Illuminate\Queue\WorkerOptions;
use VladimirYuldashev\LaravelQueueRabbitMQ\Consumer;

class TaskflowConsumer extends Consumer
{
    /** Translate the driver's legacy counter/monotonic clock to Laravel 13. */
    protected function stopIfNecessary(WorkerOptions $options, $lastRestart, $startTime = 0, $job = null, $legacyJob = null)
    {
        if (func_num_args() === 5) {
            $this->jobsProcessed = $job;
            $job = $legacyJob;
            $startTime = microtime(true) - (hrtime(true) / 1e9 - $startTime);
        }

        return parent::stopIfNecessary($options, $lastRestart, $startTime, $job);
    }

    public function stop($status = 0, $options = null, $reason = null, $connectionName = null, $queue = null)
    {
        if (is_array($status)) {
            [$status, $reason] = $status;
        }

        return parent::stop($status, $options, $reason, $connectionName, $queue);
    }

    /** Bridge the driver's two-argument call to Laravel 13's timeout signature. */
    protected function registerTimeoutHandler($connectionName, $queue, $job = null, ?WorkerOptions $options = null): void
    {
        if ($queue instanceof WorkerOptions) {
            $options = $queue;
            $job = $connectionName;
            $connectionName = $job->getConnectionName();
            $queue = $job->getQueue();
        }
        parent::registerTimeoutHandler($connectionName, $queue, $job, $options);
    }

    protected function daemonShouldRun(WorkerOptions $options, $connectionName, $queue): bool
    {
        app(WorkerIdentity::class)->beat();

        return parent::daemonShouldRun($options, $connectionName, $queue);
    }
}
