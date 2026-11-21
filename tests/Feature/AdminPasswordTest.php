<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_changes_hash_revokes_sessions_and_allows_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);
        $this->artisan('taskflow:admin-password', ['email' => $admin->email])
            ->expectsQuestion('Новый пароль (не менее 12 символов)', 'test-only-strong-password')
            ->expectsQuestion('Повторите новый пароль', 'test-only-strong-password')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('test-only-strong-password', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'test-only-strong-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_reset_does_not_promote_users_or_accept_mismatched_passwords(): void
    {
        $user = User::factory()->create();
        $this->artisan('taskflow:admin-password', ['email' => $user->email])->assertFailed();
        $this->assertSame('user', $user->fresh()->role);
        $admin = User::factory()->create(['role' => 'admin']);
        $original = $admin->password;
        $this->artisan('taskflow:admin-password', ['email' => $admin->email])
            ->expectsQuestion('Новый пароль (не менее 12 символов)', 'test-only-strong-password')
            ->expectsQuestion('Повторите новый пароль', 'different-password')
            ->assertFailed();
        $this->assertSame($original, $admin->fresh()->password);
    }
}
