<x-layout title="Задачи">
    <x-page-heading :title="auth()->user()->role === 'admin' ? 'Все задачи' : 'Мои задачи'" description="История запусков, статусы обработки и готовые результаты."><a
            class="button" href="{{ route('tasks.create') }}"><x-icon name="plus" />Создать задачу</a></x-page-heading>
    <section class="panel">
        <form method="GET" class="filters">
            <div class="field"><label for="status">Статус</label><select id="status" name="status">
                    <option value="">Все статусы</option>
                    @foreach (\App\Models\Task::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label for="type">Тип задачи</label><select id="type" name="type">
                    <option value="">Все типы</option>
                    @foreach (\App\Models\Task::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <button class="button secondary"><x-icon name="settings" />Применить</button><a class="filter-reset"
                href="{{ route('tasks.index') }}">Сбросить</a>
        </form>
        <x-task-table :tasks="$tasks" />
        <div class="table-footer">
            <span>{{ $tasks->total() ? $tasks->firstItem() . '–' . $tasks->lastItem() . ' из ' . $tasks->total() : 'Нет задач' }}</span>{{ $tasks->links('pagination') }}
        </div>
    </section>
</x-layout>
