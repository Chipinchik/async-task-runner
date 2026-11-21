<x-layout title="Вход">
    <div class="auth-heading"><span class="icon-tile"><x-icon name="lock" /></span>
        <h1>С возвращением</h1>
        <p class="muted">Войдите, чтобы продолжить работу с задачами.</p>
    </div>
    <form method="POST" action="{{ route('login') }}" class="form-stack" x-data="submitForm">@csrf
        <x-field name="email" label="Email" type="email" required autocomplete="email"
            placeholder="you@example.com" />
        <x-field name="password" label="Пароль" type="password" required autocomplete="current-password" />
        <x-submit label="Войти в TaskFlow" icon="lock" class="full-width" />
    </form>
    <p class="auth-switch">Нет аккаунта? <a href="{{ route('register') }}">Создать аккаунт</a></p>
</x-layout>
