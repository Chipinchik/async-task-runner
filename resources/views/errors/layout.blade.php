<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · TaskFlow</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="error-shell">
        <a class="brand" href="{{ url('/') }}"><span class="brand-mark"><x-icon name="activity" /></span>TaskFlow</a>
        <section class="panel padded error-card" aria-labelledby="error-title">
            <span class="badge neutral">@yield('code')</span>
            <h1 id="error-title">@yield('title')</h1>
            <p>@yield('message')</p>
            <a class="button" href="{{ url('/') }}">На главную</a>
            <a class="text-link" href="{{ route('login') }}">Войти в аккаунт</a>
        </section>
    </main>
</body>
</html>
