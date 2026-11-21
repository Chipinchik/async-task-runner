<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasUuids;

    public const TYPES = ['demo_delay' => 'Тестовая нагрузка', 'text_analysis' => 'Анализ текста', 'csv_analysis' => 'Анализ CSV'];

    public const STATUSES = ['queued' => 'В очереди', 'processing' => 'В работе', 'completed' => 'Готово', 'failed' => 'Ошибка'];

    protected $guarded = [];

    protected $hidden = ['execution_token', 'lease_expires_at'];

    protected function casts(): array
    {
        return ['input_data' => 'array', 'result_data' => 'array', 'queued_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'lease_expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class);
    }

    public function outbox(): HasMany
    {
        return $this->hasMany(TaskOutbox::class);
    }

    public function record(string $type, string $message, array $metadata = []): void
    {
        $this->events()->create(['event_type' => $type, 'message' => $message, 'metadata' => $metadata, 'worker_id' => in_array($type, ['task_created', 'task_queued'], true) ? null : $this->worker_id]);
    }
}
