<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReleaseHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->app->setLocale('ru');
    }

    public function test_security_headers_apply_to_pages_errors_and_json(): void
    {
        foreach (['/login', '/missing-page', '/api/monitor'] as $url) {
            $this->get($url)->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        config(['taskflow.hsts' => true]);
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_forwarded_https_is_trusted_only_from_configured_proxy(): void
    {
        config(['taskflow.hsts' => true, 'trustedproxy.proxies' => []]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
        config(['trustedproxy.proxies' => ['10.1.2.3']]);
        $this->get('http://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_malformed_filters_do_not_cause_server_errors(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/tasks?type[]=bad&status[]=bad')->assertOk();
        $this->get('/tasks/create?type[]=bad')->assertOk();
    }

    public function test_error_pages_are_localized_and_hide_internal_exception_details(): void
    {
        config(['app.debug' => false]);
        foreach ([401, 403, 404, 405, 413, 419, 422, 429, 500, 503] as $status) {
            Route::get('/release-error/'.$status, fn () => abort($status, 'INTERNAL_SECRET_DETAIL'));
            $this->get('/release-error/'.$status)->assertStatus($status)
                ->assertSee('TaskFlow')->assertSee('На главную')->assertDontSee('INTERNAL_SECRET_DETAIL');
        }
    }

    public function test_array_inputs_are_rejected_as_validation_errors(): void
    {
        $this->postJson('/login', ['email' => ['bad'], 'password' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/register', ['name' => 'Имя', 'email' => ['bad'], 'password' => 'password-long', 'password_confirmation' => 'password-long'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs(User::factory()->create());
        $this->patchJson('/profile', ['name' => 'Имя', 'email' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/tasks', ['type' => 'text_analysis', 'text' => ['bad']])->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->withSession(['_old_input' => ['name' => ['bad'], 'email' => ['bad']]])->get('/profile')->assertOk();
        $this->withSession(['_old_input' => ['text' => ['bad'], 'type' => ['bad']]])->get('/tasks/create')->assertOk();
    }

    public function test_task_api_omits_private_file_path_and_execution_fields(): void
    {
        $user = User::factory()->create();
        $task = app(TaskService::class)->create($user, 'csv_analysis', ['path' => 'tasks/private.csv', 'filename' => 'report.csv', 'delimiter' => ',']);
        $this->actingAs($user)->getJson('/api/tasks/'.$task->id)
            ->assertOk()->assertJsonPath('task.input_data.filename', 'report.csv')
            ->assertJsonMissingPath('task.input_data.path')->assertJsonMissingPath('task.execution_token')
            ->assertJsonMissingPath('task.lease_expires_at')->assertJsonMissingPath('task.generation');
    }

    public function test_validation_is_localized_and_user_content_is_escaped(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->patch('/profile', ['name' => str_repeat('я', 101), 'email' => 'invalid'])
            ->assertSessionHasErrors(['name' => 'Поле «Имя» — не более 100 символов.', 'email' => 'Введите корректный email.']);
        $this->get('/dashboard')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_readiness_checks_dependencies_without_disclosing_configuration(): void
    {
        Http::fake(['*' => Http::sequence()->push(['messages_ready' => 0])->push([], 503)]);
        $this->getJson('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
        Cache::forget('taskflow.broker-metrics');
        $this->getJson('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
    }

    public function test_readiness_does_not_depend_on_database_sessions_or_rate_limit_cache(): void
    {
        config(['session.driver' => 'database', 'cache.default' => 'database']);
        $database = DB::getFacadeRoot();
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new \RuntimeException('private database credentials'));
        try {
            $this->getJson('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
        } finally {
            DB::swap($database);
        }
    }

    public function test_login_and_task_creation_are_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'absent@example.test', 'password' => 'wrong'])->assertRedirect();
        }
        $this->post('/login', ['email' => 'absent@example.test', 'password' => 'wrong'])->assertStatus(429)->assertSee('Слишком много запросов');
        $this->actingAs(User::factory()->create());
        for ($i = 0; $i < 30; $i++) {
            $this->post('/tasks', ['type' => 'text_analysis', 'text' => 'текст'])->assertRedirect();
        }
        $this->post('/tasks', ['type' => 'text_analysis', 'text' => 'текст'])->assertStatus(429);
        $this->assertDatabaseCount('tasks', 30);
    }

    public function test_csrf_is_enforced_outside_test_bypass(): void
    {
        $this->app['env'] = 'local';
        $this->post('/login', ['email' => 'a@example.test', 'password' => 'invalid'])->assertStatus(419)->assertSee('Сессия истекла');
    }
}
