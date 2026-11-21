<?php

namespace App\Jobs;

use App\Services\TaskExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTask implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 0;

    public function __construct(public string $taskId, public int $generation) {}

    public function handle(TaskExecution $execution): void
    {
        $execution->run($this->taskId, $this->generation);
    }
}
