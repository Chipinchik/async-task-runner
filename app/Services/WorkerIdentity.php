<?php

namespace App\Services;

use App\Models\WorkerHeartbeat;
use Illuminate\Support\Str;

class WorkerIdentity
{
    private ?string $id = null;

    private float $lastBeat = 0;

    public function id(): string
    {
        return $this->id ??= gethostname().'-'.getmypid().'-'.Str::random(8);
    }

    public function beat(?string $taskId = null, bool $force = false): void
    {
        if (! $force && microtime(true) - $this->lastBeat < 2) {
            return;
        }
        WorkerHeartbeat::updateOrCreate(['worker_id' => $this->id()], ['hostname' => gethostname(), 'pid' => getmypid(), 'current_task_id' => $taskId, 'last_seen_at' => now()]);
        $this->lastBeat = microtime(true);
    }
}
