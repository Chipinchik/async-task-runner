<?php

namespace Tests\Feature;

use App\Jobs\ProcessTask;
use App\Models\Task;
use App\Models\TaskOutbox;
use App\Models\User;
use App\Models\WorkerHeartbeat;
use App\Services\OutboxPublisher;
use App\Services\TaskExecution;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function task(?User $user = null, string $type = 'text_analysis', array $input = ['text' => 'Привет мир привет']): Task
    {
        return app(TaskService::class)->create($user ?? User::factory()->create(), $type, $input);
    }

    public function test_registration_login_logout_and_profile(): void
    {
        $this->post('/register', ['name' => 'Анна', 'email' => 'ANNA@example.test', 'password' => 'secure-password', 'password_confirmation' => 'secure-password', 'role' => 'admin'])->assertRedirect('/dashboard');
        $user = User::first();
        $this->assertSame('user', $user->role);
        $this->assertAuthenticatedAs($user);
        $this->patch('/profile', ['name' => 'Анна Новая', 'email' => 'anna@example.test', 'role' => 'admin'])->assertRedirect();
        $this->assertSame('user', $user->fresh()->role);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => 'anna@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'anna@example.test', 'password' => 'secure-password'])->assertRedirect('/dashboard');
    }

    public function test_access_is_enforced_for_html_and_json(): void
    {
        $task = $this->task();
        $this->getJson('/api/tasks/'.$task->id)->assertUnauthorized();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/tasks/'.$task->id)->assertForbidden();
        $this->getJson('/api/tasks/'.$task->id)->assertForbidden();
        $this->getJson('/api/monitor')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->post('/admin/demo')->assertForbidden();
        $this->post('/tasks/'.$task->id.'/retry')->assertForbidden();
        $this->get('/tasks')->assertOk()->assertDontSee(substr($task->id, -8));
        $this->actingAs($task->user)->getJson('/api/tasks/'.$task->id)->assertOk()->assertJsonPath('task.status', 'queued')->assertJsonMissingPath('task.execution_token');
    }

    public function test_task_validation_and_durable_creation_without_execution(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());
        $this->post('/tasks', ['type' => 'demo_delay', 'seconds' => 31])->assertSessionHasErrors('seconds');
        $this->post('/tasks', ['type' => 'invalid'])->assertSessionHasErrors('type');
        $this->post('/tasks', ['type' => 'text_analysis', 'text' => str_repeat('я', 100001)])->assertSessionHasErrors('text');
        $this->post('/tasks', ['type' => 'demo_delay', 'seconds' => 10])->assertRedirect();
        $task = Task::first();
        $this->assertSame('queued', $task->status);
        $this->assertNull($task->worker_id);
        $this->assertNull($task->queued_at);
        $this->assertDatabaseCount('task_outbox', 1);
        $this->assertSame(['task_created'], $task->events->pluck('event_type')->all());
        Queue::assertNothingPushed();
    }

    public function test_uploaded_csv_is_private_and_size_is_validated(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $this->post('/tasks', ['type' => 'csv_analysis', 'delimiter' => ',', 'csv' => UploadedFile::fake()->create('large.csv', 5121, 'text/csv')])->assertSessionHasErrors('csv');
        $this->post('/tasks', ['type' => 'csv_analysis', 'delimiter' => ',', 'csv' => UploadedFile::fake()->createWithContent('data.csv', "name,amount\nИван,5\n")])->assertRedirect();
        $task = Task::first();
        Storage::disk('local')->assertExists($task->input_data['path']);
        $this->assertStringStartsWith('tasks/', $task->input_data['path']);
    }

    public function test_publication_records_event_only_after_success_and_does_not_repeat(): void
    {
        Queue::fake();
        $task = $this->task();
        $publisher = app(OutboxPublisher::class);
        $this->assertSame(1, $publisher->publish());
        $this->assertSame(0, $publisher->publish());
        Queue::assertPushed(ProcessTask::class, 1);
        $this->assertNotNull($task->fresh()->queued_at);
        $this->assertDatabaseHas('task_events', ['task_id' => $task->id, 'event_type' => 'task_queued']);
    }

    public function test_broker_failure_retains_outbox_without_false_queued_event(): void
    {
        $task = $this->task();
        $connection = \Mockery::mock();
        $connection->shouldReceive('push')->once()->andThrow(new \RuntimeException('broker unavailable'));
        $connection->shouldReceive('resetAfterFailure')->once();
        Queue::shouldReceive('connection')->with('rabbitmq')->andReturn($connection);
        Queue::shouldReceive('connected')->with('rabbitmq')->andReturn(true);
        $this->assertSame(0, app(OutboxPublisher::class)->publish());
        $this->assertNull($task->fresh()->queued_at);
        $this->assertNull(TaskOutbox::first()->published_at);
        $this->assertNotNull(TaskOutbox::first()->last_error);
        $this->assertDatabaseMissing('task_events', ['event_type' => 'task_queued']);
    }

    public function test_text_execution_is_idempotent_and_old_generation_is_ignored(): void
    {
        $task = $this->task();
        $execution = app(TaskExecution::class);
        $execution->run($task->id, 0);
        $this->assertSame('queued', $task->fresh()->status);
        $execution->run($task->id, 1);
        $execution->run($task->id, 1);
        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertSame(3, $task->result_data['words']);
        $this->assertNotNull($task->worker_id);
        $this->assertNull($task->progress);
        $this->assertSame(1, $task->events()->where('event_type', 'task_started')->count());
        $this->assertSame(1, $task->events()->where('event_type', 'task_completed')->count());
    }

    public function test_real_delay_records_measured_progress_and_duration(): void
    {
        $task = $this->task(type: 'demo_delay', input: ['seconds' => 3]);
        app(TaskExecution::class)->run($task->id, 1);
        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertGreaterThanOrEqual(3000, $task->duration_ms);
        $this->assertSame([33, 66, 100], $task->events()->where('event_type', 'task_progress')->orderBy('id')->get()->pluck('metadata.progress')->all());
    }

    public function test_csv_execution_and_retries_are_bounded_and_manual_retry_is_safe(): void
    {
        Storage::fake('local');
        $task = $this->task(type: 'csv_analysis', input: ['path' => 'tasks/missing.csv', 'delimiter' => ',']);
        $execution = app(TaskExecution::class);
        for ($generation = 1; $generation <= 3; $generation++) {
            $execution->run($task->id, $generation);
        }
        $task->refresh();
        $this->assertSame('failed', $task->status);
        $this->assertSame(3, $task->attempts);
        $this->assertSame(3, $task->events()->where('event_type', 'task_failed')->count());
        $this->assertSame(2, $task->events()->where('event_type', 'task_retried')->count());
        $this->assertTrue(TaskOutbox::latest('id')->first()->available_at->isFuture());
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/tasks/'.$task->id.'/retry')->assertRedirect();
        $this->post('/tasks/'.$task->id.'/retry')->assertForbidden();
        $task->refresh();
        $this->assertSame(4, $task->generation);
        $this->assertSame(0, $task->attempts);
        Storage::disk('local')->put('tasks/missing.csv', "name,value\nИван,2\nАнна,4\n");
        $execution->run($task->id, 3);
        $this->assertSame('queued', $task->fresh()->status);
        $execution->run($task->id, 4);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertEquals(3, $task->fresh()->result_data['columns'][1]['avg']);
    }

    public function test_expired_execution_is_recovered_and_live_execution_is_not(): void
    {
        $task = $this->task();
        $task->update(['status' => 'processing', 'attempts' => 1, 'execution_token' => '00000000-0000-4000-8000-000000000001', 'lease_expires_at' => now()->subSecond(), 'started_at' => now()->subMinutes(2)]);
        $live = $this->task();
        $live->update(['status' => 'processing', 'attempts' => 1, 'execution_token' => '00000000-0000-4000-8000-000000000002', 'lease_expires_at' => now()->addMinute(), 'started_at' => now()]);
        $this->assertSame(1, app(TaskExecution::class)->recoverExpired());
        $this->assertSame('queued', $task->fresh()->status);
        $this->assertSame(2, $task->fresh()->generation);
        $this->assertSame('processing', $live->fresh()->status);
        app(TaskExecution::class)->fail($task->id, '00000000-0000-4000-8000-000000000001', 'late failure');
        $this->assertSame(2, $task->fresh()->generation);
    }

    public function test_monitoring_returns_real_metrics_and_honest_heartbeat_ttl(): void
    {
        Http::fake(['*' => Http::response(['messages_ready' => 4, 'messages_unacknowledged' => 2, 'consumers' => 3])]);
        WorkerHeartbeat::create(['worker_id' => 'stale', 'hostname' => 'test', 'pid' => 1, 'last_seen_at' => now()->subMinute()]);
        WorkerHeartbeat::create(['worker_id' => 'live', 'hostname' => 'test', 'pid' => 2, 'last_seen_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->getJson('/api/monitor')->assertOk()->assertJsonPath('broker.messages_ready', 4);
        $workers = collect($response->json('workers'))->keyBy('worker_id');
        $this->assertFalse($workers['stale']['online']);
        $this->assertTrue($workers['live']['online']);
        $response->assertJsonMissingPath('broker.password');
    }

    public function test_renewed_lease_and_running_delivery_are_not_overwritten(): void
    {
        $task = $this->task();
        $token = '00000000-0000-4000-8000-000000000001';
        $task->update(['status' => 'processing', 'attempts' => 1, 'execution_token' => $token, 'lease_expires_at' => now()->addMinute(), 'started_at' => now()]);
        $execution = app(TaskExecution::class);
        $execution->run($task->id, 1);
        $execution->fail($task->id, $token, 'Stale reaper snapshot', true);
        $this->assertSame('processing', $task->fresh()->status);
        $this->assertSame(1, $task->fresh()->attempts);
        $this->assertSame(1, $task->events()->count());
    }

    public function test_unavailable_broker_metrics_are_null_and_admin_demo_creates_six_jobs(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/monitor')->assertOk()->assertJsonPath('broker.available', false)->assertJsonPath('broker.messages_ready', null);
        $this->post('/admin/demo')->assertRedirect('/monitor');
        $this->assertSame(6, Task::where('type', 'demo_delay')->count());
        $this->assertDatabaseCount('task_outbox', 6);
    }

    public function test_all_html_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $user = User::factory()->create(['role' => 'admin']);
        $task = $this->task($user);
        $this->actingAs($user);
        foreach (['/dashboard', '/tasks', '/tasks/create', '/tasks/'.$task->id, '/monitor', '/admin', '/profile'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_monitor_event_cursor_does_not_repeat_old_events(): void
    {
        Http::fake(['*' => Http::response([], 503)]);
        $task = $this->task();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $cursor = $this->getJson('/api/monitor')->json('cursor');
        $this->getJson('/api/monitor?after='.$cursor)->assertJsonCount(0, 'events');
        $task->record('task_progress', 'Новый факт');
        $this->getJson('/api/monitor?after='.$cursor)->assertJsonCount(1, 'events')->assertJsonPath('events.0.message', 'Новый факт');
    }
}
