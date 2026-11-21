$ErrorActionPreference = 'Stop'
$taskflowRoot = Split-Path $PSScriptRoot -Parent
$taskflowEnv = Join-Path $taskflowRoot '.env'
if (-not (Test-Path -LiteralPath $taskflowEnv)) {
    Copy-Item -LiteralPath (Join-Path $taskflowRoot '.env.example') -Destination $taskflowEnv
}
$taskflowContent = [IO.File]::ReadAllText($taskflowEnv)
if ($taskflowContent -notmatch '(?m)^APP_KEY=base64:.+') {
    $taskflowBytes = New-Object byte[] 32
    $taskflowRng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $taskflowRng.GetBytes($taskflowBytes)
    $taskflowRng.Dispose()
    $taskflowKey = 'APP_KEY=base64:' + [Convert]::ToBase64String($taskflowBytes)
    if ($taskflowContent -match '(?m)^APP_KEY=.*$') {
        $taskflowContent = [regex]::Replace($taskflowContent, '(?m)^APP_KEY=.*$', $taskflowKey)
    } else { $taskflowContent += "`n$taskflowKey`n" }
    [IO.File]::WriteAllText($taskflowEnv, $taskflowContent, (New-Object Text.UTF8Encoding $false))
}
Write-Host 'Локальный .env готов. Запустите docker compose up -d --build --scale worker=3'
