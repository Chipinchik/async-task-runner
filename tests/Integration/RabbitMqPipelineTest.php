<?php

namespace Tests\Integration;

use App\Jobs\ProcessTask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkerHeartbeat;
use App\Services\BrokerMetrics;
use App\Services\TaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Runs against the existing Compose stack. Never uses RefreshDatabase or Queue::fake. */
class RabbitMqPipelineTest extends TestCase
{
    public function test_six_real_jobs_are_processed_in_parallel_and_duplicates_are_safe(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName(), 'Run inside Docker Compose.');
        $workers = WorkerHeartbeat::where('last_seen_at', '>', now()->subSeconds(15))->count();
        $expectedWorkers = (int) env('TASKFLOW_TEST_WORKERS', 3);
        $this->assertContains($expectedWorkers, [1, 3]);
        $this->assertGreaterThanOrEqual($expectedWorkers, $workers, 'Start the expected worker count before integration tests.');
        $user = User::factory()->create();
        $ids = [];
        $start = microtime(true);
        try {
            for ($i = 0; $i < 6; $i++) {
                $ids[] = app(TaskService::class)->create($user, 'demo_delay', ['seconds' => 10])->id;
            }
            $maxProcessing = 0;
            $maxUnacked = 0;
            do {
                usleep(500000);
                $tasks = Task::whereIn('id', $ids)->get();
                $maxProcessing = max($maxProcessing, $tasks->where('status', 'processing')->count());
                $metrics = app(BrokerMetrics::class)->read();
                $maxUnacked = max($maxUnacked, $metrics['messages_unacknowledged'] ?? 0);
                if ($tasks->where('status', 'completed')->count() === 6) {
                    break;
                }
            } while (microtime(true) - $start < 90);
            $elapsed = microtime(true) - $start;
            $this->assertSame(6, $tasks->where('status', 'completed')->count());
            $this->assertSame($expectedWorkers, $maxProcessing);
            $this->assertSame($expectedWorkers, $tasks->pluck('worker_id')->unique()->count());
            $this->assertGreaterThanOrEqual(1, $maxUnacked);
            $this->assertLessThan($expectedWorkers === 1 ? 85 : 45, $elapsed);
            if ($expectedWorkers === 1) {
                $this->assertGreaterThanOrEqual(60, $elapsed);
            }
            foreach ($tasks as $task) {
                $this->assertSame(1, $task->attempts);
                $this->assertSame(10, $task->events()->where('event_type', 'task_progress')->count());
                $this->assertSame(1, $task->events()->where('event_type', 'task_queued')->count());
                $this->assertGreaterThanOrEqual(10, $task->result_data['actual_seconds']);
            }
            $task = $tasks->first();
            Queue::connection('rabbitmq')->push(new ProcessTask($task->id, $task->generation));
            sleep(3);
            $this->assertSame(1, $task->fresh()->attempts);
            $this->assertSame(1, $task->events()->where('event_type', 'task_completed')->count());
            fwrite(STDOUT, sprintf("\nREAL PIPELINE: 6 × 10s, %.2fs, %d parallel, %d distinct workers, %d max unacked.\n", $elapsed, $maxProcessing, $tasks->pluck('worker_id')->unique()->count(), $maxUnacked));
        } finally {
            $user->delete();
        }
    }

    public function test_real_text_csv_and_bounded_failure(): void
    {
        $user = User::factory()->create();
        $path = 'tasks/integration-'.bin2hex(random_bytes(8)).'.csv';
        Storage::disk('local')->put($path, "name,value\nАнна,2\nИван,4\n");
        try {
            $service = app(TaskService::class);
            $text = $service->create($user, 'text_analysis', ['text' => 'Привет мир привет']);
            $csv = $service->create($user, 'csv_analysis', ['path' => $path, 'delimiter' => ',']);
            $failed = $service->create($user, 'csv_analysis', ['path' => 'tasks/does-not-exist.csv', 'delimiter' => ',']);
            $start = microtime(true);
            do {
                usleep(500000);
                if ($text->refresh()->status === 'completed' && $csv->refresh()->status === 'completed' && $failed->refresh()->status === 'failed') {
                    break;
                }
            } while (microtime(true) - $start < 50);
            $this->assertSame('completed', $text->refresh()->status);
            $this->assertSame(3, $text->result_data['words']);
            $this->assertSame('completed', $csv->refresh()->status);
            $this->assertEquals(3, $csv->result_data['columns'][1]['avg']);
            $this->assertSame('failed', $failed->refresh()->status);
            $this->assertSame(3, $failed->attempts);
            $this->assertSame(2, $failed->events()->where('event_type', 'task_retried')->count());
            $service->retry($failed);
            $failed->refresh()->update(['input_data' => ['path' => $path, 'delimiter' => ',']]);
            $start = microtime(true);
            do {
                usleep(500000);
            } while ($failed->refresh()->status !== 'completed' && microtime(true) - $start < 15);
            $this->assertSame('completed', $failed->refresh()->status);
            fwrite(STDOUT, "\nREAL HANDLERS: Unicode text, shared CSV, 3 bounded attempts and manual retry passed.\n");
        } finally {
            $user->delete();
            Storage::disk('local')->delete($path);
        }
    }
}
