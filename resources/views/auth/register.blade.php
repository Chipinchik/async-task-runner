<x-layout title="Регистрация">
    <div class="auth-heading"><span class="icon-tile"><x-icon name="user" /></span>
        <h1>Создайте аккаунт</h1>
        <p class="muted">Ваши задачи, прогресс и результаты — в одном месте.</p>
    </div>
    <form method="POST" action="{{ route('register') }}" class="form-stack" x-data="submitForm">@csrf
        <x-field name="name" label="Имя" required maxlength="100" autocomplete="name"
            placeholder="Как к вам обращаться?" />
        <x-field name="email" label="Email" type="email" required autocomplete="email"
            placeholder="you@example.com" />
        <x-field name="password" label="Пароль" type="password" required minlength="10" autocomplete="new-password"
            hint="Не менее 10 символов." />
        <x-field name="password_confirmation" label="Повторите пароль" type="password" required
            autocomplete="new-password" />
        <x-submit label="Создать аккаунт" icon="user" class="full-width" />
    </form>
    <p class="auth-switch">Уже зарегистрированы? <a href="{{ route('login') }}">Войти</a></p>
</x-layout>
