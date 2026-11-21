#Requires -Version 7.3
# Only for an idle local Compose stack. Own fixtures are removed in finally.
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
$taskflowTaskId = (& docker compose exec -T app php artisan tinker --execute '$user = App\Models\User::factory()->create(); $task = app(App\Services\TaskService::class)->create($user, "demo_delay", ["seconds" => 10]); echo $task->id;').Trim()
if ($LASTEXITCODE -ne 0 -or $taskflowTaskId -notmatch '^[0-9a-f-]{36}$') { throw 'Could not create isolated graceful-stop fixture.' }
try {
    $taskflowDeadline = [DateTime]::UtcNow.AddSeconds(30)
    do {
        $taskflowState = (& docker compose exec -T postgres psql -U taskflow -d taskflow -tAc "SELECT status FROM tasks WHERE id='$taskflowTaskId'").Trim()
        if ($taskflowState -eq 'processing') { break }
        Start-Sleep -Milliseconds 200
    } while ([DateTime]::UtcNow -lt $taskflowDeadline)
    if ($taskflowState -ne 'processing') { throw 'Task did not start in time.' }
    & docker compose stop worker
    if ($LASTEXITCODE -ne 0) { throw 'Graceful stop failed.' }
    $taskflowState = (& docker compose exec -T postgres psql -U taskflow -d taskflow -tAc "SELECT status || ':' || attempts FROM tasks WHERE id='$taskflowTaskId'").Trim()
    if ($taskflowState -ne 'completed:1') { throw "Expected completed:1, got $taskflowState" }
    $taskflowContainers = & docker compose ps -aq worker
    foreach ($taskflowContainer in $taskflowContainers) {
        $taskflowExit = (& docker inspect --format '{{.State.ExitCode}}' $taskflowContainer).Trim()
        if ($taskflowExit -ne '0') { throw "Worker exit code: $taskflowExit" }
    }
    Write-Host 'PASS: SIGTERM completed the in-flight task on attempt 1; all Workers exited with code 0.'
} finally {
    & docker compose start worker
    $taskflowCleanup = 'App\Models\Task::find("' + $taskflowTaskId + '")?->user?->delete();'
    & docker compose exec -T app php artisan tinker --execute $taskflowCleanup
}
