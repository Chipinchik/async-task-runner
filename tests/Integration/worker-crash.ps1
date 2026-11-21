#Requires -Version 7.3
# Run only against the local TaskFlow Compose stack, with no unrelated active tasks.
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
$taskflowTaskId = (& docker compose exec -T app php artisan tinker --execute '$user = App\Models\User::factory()->create(); $task = app(App\Services\TaskService::class)->create($user, "demo_delay", ["seconds" => 10]); echo $task->id;').Trim()
if ($LASTEXITCODE -ne 0 -or $taskflowTaskId -notmatch '^[0-9a-f-]{36}$') { throw 'Could not create isolated crash-test task.' }
try {
    $taskflowDeadline = [DateTime]::UtcNow.AddSeconds(30)
    do {
        $taskflowState = (& docker compose exec -T postgres psql -U taskflow -d taskflow -tAc "SELECT status FROM tasks WHERE id='$taskflowTaskId'").Trim()
        if ($taskflowState -eq 'processing') { break }
        Start-Sleep -Milliseconds 200
    } while ([DateTime]::UtcNow -lt $taskflowDeadline)
    if ($taskflowState -ne 'processing') { throw 'Task did not start in time.' }
    & docker compose kill -s SIGKILL worker
    if ($LASTEXITCODE -ne 0) { throw 'Worker kill failed.' }
    & docker compose start worker
    $taskflowDeadline = [DateTime]::UtcNow.AddSeconds(140)
    do {
        $taskflowState = (& docker compose exec -T postgres psql -U taskflow -d taskflow -tAc "SELECT status || ':' || attempts FROM tasks WHERE id='$taskflowTaskId'").Trim()
        if ($taskflowState -eq 'completed:2') { break }
        Start-Sleep -Seconds 2
    } while ([DateTime]::UtcNow -lt $taskflowDeadline)
    if ($taskflowState -ne 'completed:2') { throw "Expected completed:2, got $taskflowState" }
    & docker compose exec -T postgres psql -U taskflow -d taskflow -c "SELECT event_type, worker_id, message FROM task_events WHERE task_id='$taskflowTaskId' AND event_type <> 'task_progress' ORDER BY id;"
    Write-Host 'PASS: SIGKILL during execution, lease recovery, second attempt completed.'
} finally {
    & docker compose start worker
    # Only the user created by this script, identified by its validated task UUID.
    $taskflowCleanup = 'App\Models\Task::find("' + $taskflowTaskId + '")?->user?->delete();'
    & docker compose exec -T app php artisan tinker --execute $taskflowCleanup
}
