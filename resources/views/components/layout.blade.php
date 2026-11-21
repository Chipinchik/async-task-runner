<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f7f8fc">
    <title>{{ $title ?? 'Обзор' }} · TaskFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <a class="skip-link" href="#main-content">К содержимому</a>
    @auth
        <div class="app-shell" x-data="{ navOpen: false }" @keydown.escape.window="navOpen = false">
            <aside class="sidebar" :class="{ 'is-open': navOpen }">
                <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark"><x-icon
                            name="activity" /></span>TaskFlow<span class="brand-beta">workspace</span></a>
                <div class="workspace-label"><span class="workspace-avatar">T</span>
                    <div><strong>Рабочее пространство</strong><small>Локальный проект</small></div>
                </div>
                <p class="nav-label">ПРОЕКТ</p>
                <nav id="main-nav" aria-label="Основная навигация">
                    @foreach ([['dashboard', 'grid', 'Обзор'], ['tasks.index', 'tasks', 'Задачи'], ['tasks.create', 'plus', 'Создать задачу']] as [$route, $icon, $label])
                        <a @class([
                            'nav-link',
                            'active' =>
                                request()->routeIs($route) ||
                                ($route === 'tasks.index' && request()->routeIs('tasks.show')),
                        ]) @if (request()->routeIs($route)) aria-current="page" @endif
                            href="{{ route($route) }}"><x-icon :name="$icon" />{{ $label }}</a>
                    @endforeach
                    @can('admin')
                        <p class="nav-label">СИСТЕМА</p>
                        <a @class(['nav-link', 'active' => request()->routeIs('monitor')]) @if (request()->routeIs('monitor')) aria-current="page" @endif
                            href="{{ route('monitor') }}"><x-icon name="activity" />Мониторинг</a>
                        <a @class(['nav-link', 'active' => request()->routeIs('admin')]) @if (request()->routeIs('admin')) aria-current="page" @endif
                            href="{{ route('admin') }}"><x-icon name="settings" />Администрирование</a>
                    @endcan
                </nav>
                <div class="sidebar-bottom">
                    <div class="sidebar-note"><x-icon name="server" /><strong>Создано для параллельности</strong>
                        <p>Одна очередь. Независимые обработчики. Прозрачный результат.</p>
                    </div>
                    <div class="account-row"><a href="{{ route('profile') }}" class="account-link"><span
                                class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? 'Администратор' : 'Пользователь' }}</small></span></a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button"
                                aria-label="Выйти"><x-icon name="logout" /></button></form>
                    </div>
                </div>
            </aside>
            <main class="main" id="main-content">
                <header class="topbar"><button type="button" class="icon-button mobile-menu" @click="navOpen = !navOpen"
                        :aria-expanded="navOpen" aria-controls="main-nav" aria-label="Меню навигации"><x-icon
                            name="menu" /></button>
                    <div class="breadcrumb">Рабочее пространство <span>/</span><strong>{{ $title ?? 'Обзор' }}</strong>
                    </div><span class="environment">Локальная среда</span>
                </header>
                <div class="content">
                    @if (session('success'))
                        <div class="notice success" role="status"><x-icon
                                name="check" /><span>{{ session('success') }}</span></div>
                    @endif
                    <x-errors />{{ $slot }}
                </div>
                <footer><span>TaskFlow <span class="footer-separator">/</span> Асинхронная обработка</span><span>Laravel ·
                        RabbitMQ · PostgreSQL</span></footer>
            </main>
        </div>
    @else
        <main class="auth-shell" id="main-content">
            <section class="auth-story">
                <a href="{{ route('login') }}" class="brand"><span class="brand-mark"><x-icon
                            name="activity" /></span>TaskFlow</a>
                <div class="auth-story-content"><span class="eyebrow">ВАШЕ РАБОЧЕЕ ПРОСТРАНСТВО</span>
                    <h2>Сложная обработка.<br><span>Простой контроль.</span></h2>
                    <p>Запускайте задачи в фоне, наблюдайте за каждым этапом и получайте готовые результаты.</p>
                    <div class="auth-flow">
                        <div><x-icon name="code" /><span>Создание</span></div><span class="flow-line"></span>
                        <div><x-icon name="queue" /><span>Очередь</span></div><span class="flow-line"></span>
                        <div><x-icon name="check" /><span>Результат</span></div>
                    </div>
                    <div class="auth-feature"><x-icon name="activity" /><span>Параллельная обработка, прозрачная
                            история</span></div>
                    <div class="auth-feature"><x-icon name="lock" /><span>Ваши задачи доступны только вам и
                            администратору</span></div>
                </div>
                <p class="auth-footnote">Laravel · RabbitMQ · PostgreSQL</p>
            </section>
            <section class="auth-form-area">
                <div class="auth-card"><x-errors />{{ $slot }}</div>
                <p class="auth-form-footer">TaskFlow · пространство для ваших задач</p>
            </section>
        </main>
    @endauth
</body>

</html>
