<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskOutbox extends Model
{
    protected $table = 'task_outbox';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
