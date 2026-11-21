<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class TaskController extends Controller
{
    public function dashboard(Request $request): View
    {
        $query = Task::query()->when($request->user()->role !== 'admin', fn ($query) => $query->where('user_id', $request->user()->id));

        return view('dashboard', ['counts' => (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'), 'tasks' => $query->latest()->limit(8)->get()]);
    }

    public function index(Request $request): View
    {
        $query = Task::query()->when($request->user()->role !== 'admin', fn ($query) => $query->where('user_id', $request->user()->id));
        if (array_key_exists((string) $request->status, Task::STATUSES)) {
            $query->where('status', $request->status);
        }
        if (array_key_exists((string) $request->type, Task::TYPES)) {
            $query->where('type', $request->type);
        }

        return view('tasks.index', ['tasks' => $query->latest()->paginate(15)->withQueryString()]);
    }

    public function show(Task $task): View
    {
        Gate::authorize('view', $task);

        return view('tasks.show', compact('task'));
    }

    public function store(StoreTaskRequest $request, TaskService $service): RedirectResponse
    {
        $input = match ($request->type) {
            'demo_delay' => ['seconds' => (int) $request->seconds],
            'text_analysis' => ['text' => $request->text],
            'csv_analysis' => ['path' => $request->file('csv')->store('tasks', 'local'), 'filename' => $request->file('csv')->getClientOriginalName(), 'delimiter' => $request->delimiter],
        };
        abort_if(isset($input['path']) && ! $input['path'], 500, 'Не удалось сохранить CSV.');
        try {
            $task = $service->create($request->user(), $request->type, $input);
        } catch (Throwable $exception) {
            if (isset($input['path'])) {
                Storage::disk('local')->delete($input['path']);
            } throw $exception;
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Задание сохранено. Диспетчер отправит его в RabbitMQ; подтверждение публикации появится в истории.');
    }

    public function retry(Task $task, TaskService $service): RedirectResponse
    {
        Gate::authorize('retry', $task);
        $service->retry($task);

        return back()->with('success', 'Повтор запланирован.');
    }

    public function demo(Request $request, TaskService $service): RedirectResponse
    {
        Gate::authorize('admin');
        for ($i = 0; $i < 6; $i++) {
            $service->create($request->user(), 'demo_delay', ['seconds' => 10]);
        }

        return redirect()->route('monitor')->with('success', 'Созданы 6 реальных задач по 10 секунд.');
    }
}
