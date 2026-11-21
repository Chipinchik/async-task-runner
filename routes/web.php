<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/ready', ReadinessController::class)->withoutMiddleware('web');
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/profile', 'auth.profile')->name('profile');
    Route::patch('/profile', [AuthController::class, 'profile']);
    Route::get('/dashboard', [TaskController::class, 'dashboard'])->name('dashboard');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::view('/tasks/create', 'tasks.create')->name('tasks.create');
    Route::post('/tasks', [TaskController::class, 'store'])->middleware('throttle:30,1')->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/api/tasks/{task}', [MonitoringController::class, 'task'])->name('api.tasks.show');
    Route::middleware('can:admin')->group(function (): void {
        Route::view('/monitor', 'monitor')->name('monitor');
        Route::view('/admin', 'admin')->name('admin');
        Route::get('/api/monitor', [MonitoringController::class, 'snapshot'])->name('api.monitor');
        Route::post('/tasks/{task}/retry', [TaskController::class, 'retry'])->middleware('throttle:10,1')->name('tasks.retry');
        Route::post('/admin/demo', [TaskController::class, 'demo'])->middleware('throttle:3,1')->name('admin.demo');
    });
});
