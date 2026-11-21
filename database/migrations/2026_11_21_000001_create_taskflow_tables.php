<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 16)->default('user');
        });
        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('status', 16)->default('queued')->index();
            $table->json('input_data');
            $table->json('result_data')->nullable();
            $table->unsignedTinyInteger('progress')->nullable();
            $table->string('worker_id')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->unsignedInteger('generation')->default(1);
            $table->uuid('execution_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('task_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->string('worker_id')->nullable();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['task_id', 'id']);
        });
        Schema::create('task_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->timestamp('available_at')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('publish_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'generation']);
        });
        Schema::create('worker_heartbeats', function (Blueprint $table): void {
            $table->string('worker_id')->primary();
            $table->string('hostname');
            $table->unsignedInteger('pid');
            $table->foreignUuid('current_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamp('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_heartbeats');
        Schema::dropIfExists('task_outbox');
        Schema::dropIfExists('task_events');
        Schema::dropIfExists('tasks');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
