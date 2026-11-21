@props(['monitor' => false])
<div class="poll-status">
    <span class="live-dot" :class="{ offline: !{{ $monitor ? 'fresh()' : 'connected' }} }" aria-hidden="true"></span>
    <span x-text="connection"></span>
    <span class="sr-only" role="status" x-text="connected ? 'Данные обновляются' : connection"></span>
    <a href="{{ route('login') }}" x-cloak x-show="connection === 'Сессия завершена. Войдите снова.'">Войти снова</a>
</div>
