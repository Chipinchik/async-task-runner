<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Обзор' }} · TaskFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark">⌁</span> TaskFlow<span class="brand-caption">ASYNC WORKSPACE</span></a>
        @auth
        <p class="nav-label">РАБОЧЕЕ ПРОСТРАНСТВО</p>
        <nav aria-label="Основная навигация">
            <a @class(['nav-link', 'active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span>◫</span> Обзор</a>
            <a @class(['nav-link', 'active' => request()->routeIs('tasks.index','tasks.show')]) href="{{ route('tasks.index') }}"><span>▤</span> {{ auth()->user()->role === 'admin' ? 'Все задачи' : 'Мои задачи' }}</a>
            <a @class(['nav-link', 'active' => request()->routeIs('tasks.create')]) href="{{ route('tasks.create') }}"><span>＋</span> Создать задачу</a>
            @can('admin')
            <a @class(['nav-link', 'active' => request()->routeIs('monitor')]) href="{{ route('monitor') }}"><span>⌁</span> Мониторинг</a>
            <a @class(['nav-link', 'active' => request()->routeIs('admin')]) href="{{ route('admin') }}"><span>⚙</span> Администрирование</a>
            @endcan
        </nav>
        <div class="sidebar-bottom"><p>Laravel · RabbitMQ · PostgreSQL</p><a href="{{ route('profile') }}">{{ auth()->user()->name }}</a><small>{{ auth()->user()->role === 'admin' ? 'Администратор' : 'Пользователь' }}</small>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-button">Выйти</button></form></div>
        @else
        <div class="sidebar-bottom"><p>Задачи выполняются независимо.<br>Результаты всегда на виду.</p></div>
        @endauth
    </aside>
    <main class="main">
        <header class="topbar"><span>Распределённая обработка задач</span><span class="environment">LOCAL WORKSPACE</span></header>
        <div class="content">
            @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="notice error" role="alert"><strong>Проверьте данные</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </div>
        <footer>TaskFlow <span>События и результаты реального выполнения</span></footer>
    </main>
</div>
</body>
</html>
