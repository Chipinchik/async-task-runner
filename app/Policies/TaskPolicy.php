<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $user->role === 'admin' || $task->user_id === $user->id;
    }

    public function retry(User $user, Task $task): bool
    {
        return $user->role === 'admin' && $task->status === 'failed';
    }
}
