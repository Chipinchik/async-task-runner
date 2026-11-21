<x-layout title="Создать задачу">
    <x-page-heading title="Новая задача"
        description="Выберите тип обработки и добавьте данные. Остальное сделаем в фоне." />
    <div class="creation-grid">
        <section class="panel padded" x-data="{ type: @js(old('type', array_key_exists(request('type', 'demo_delay'), \App\Models\Task::TYPES) ? request('type', 'demo_delay') : 'demo_delay')) }">
            <form method="POST" enctype="multipart/form-data" action="{{ route('tasks.store') }}" class="form-stack"
                x-data="submitForm">@csrf
                <fieldset class="type-picker" id="type">
                    <legend>Что будем обрабатывать?</legend>
                    @foreach (['demo_delay' => ['clock', 'Тестовая задержка', '3–30 секунд'], 'text_analysis' => ['text', 'Анализ текста', 'Русский и английский'], 'csv_analysis' => ['file', 'Анализ CSV', 'Таблица до 5 МБ']] as $value => $info)
                        <label class="type-choice" :class="{ selected: type === '{{ $value }}' }"><input
                                type="radio" name="type" value="{{ $value }}" x-model="type"><x-icon
                                :name="$info[0]" /><strong>{{ $info[1] }}</strong><small>{{ $info[2] }}</small></label>
                    @endforeach
                </fieldset>
                @error('type')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <div x-show="type === 'demo_delay'">
                    <div class="form-section-title"><x-icon name="clock" />
                        <h2>Параметры задержки</h2>
                    </div>
                    <x-field name="seconds" label="Длительность, секунды" type="number" :value="10"
                        min="3" max="30" x-bind:disabled="type !== 'demo_delay'"
                        x-bind:required="type === 'demo_delay'"
                        hint="От 3 до 30 секунд. Прогресс обновляется во время реального выполнения." />
                </div>
                <div x-cloak x-show="type === 'text_analysis'">
                    <div class="form-section-title"><x-icon name="text" />
                        <h2>Текст для анализа</h2>
                    </div>
                    <div class="field"><label for="text">Исходный текст</label>
                        <textarea id="text" name="text" rows="9" maxlength="100000" :disabled="type !== 'text_analysis'"
                            :required="type === 'text_analysis'" aria-describedby="text-hint text-error"
                            aria-invalid="{{ $errors->has('text') ? 'true' : 'false' }}"
                            placeholder="Вставьте текст на русском или английском…">{{ old('text') }}</textarea><small class="help" id="text-hint">До 100 000 символов. Посчитаем
                            слова, строки, символы и частоты слов.</small><span class="field-error" id="text-error">
                            @error('text')
                                {{ $message }}
                            @enderror
                        </span>
                    </div>
                </div>
                <div x-cloak x-show="type === 'csv_analysis'">
                    <div class="form-section-title"><x-icon name="file" />
                        <h2>Файл для анализа</h2>
                    </div>
                    <div class="upload-zone"><x-icon name="upload" /><label class="field-label" for="csv">Выберите
                            CSV-файл</label><input type="file" id="csv" name="csv" accept=".csv,.txt"
                            :disabled="type !== 'csv_analysis'" :required="type === 'csv_analysis'"
                            aria-describedby="csv-hint csv-error"
                            aria-invalid="{{ $errors->has('csv') ? 'true' : 'false' }}">
                        <p id="csv-hint">UTF-8 · до 5 МБ · первая строка — заголовки</p><span class="field-error"
                            id="csv-error">
                            @error('csv')
                                {{ $message }}
                            @enderror
                        </span>
                    </div>
                    <div class="field"><label for="delimiter">Разделитель столбцов</label><select id="delimiter"
                            name="delimiter" :disabled="type !== 'csv_analysis'">
                            <option value=",">Запятая (,)</option>
                            <option value=";" @selected(old('delimiter') === ';')>Точка с запятой (;)</option>
                            <option value="&#9;" @selected(old('delimiter') === "\t")>Табуляция</option>
                        </select>
                        @error('delimiter')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                        <small class="help">До 100 столбцов с уникальными названиями. Структура проверяется при
                            обработке.</small>
                    </div>
                </div>
                <div class="form-actions"><x-submit label="Запустить обработку" icon="play" /><a
                        href="{{ route('tasks.index') }}">Отмена</a></div>
            </form>
        </section>
        <aside class="panel creation-aside">
            <h3>Что будет дальше?</h3>
            <div class="process-step"><x-icon name="tasks" />
                <div><strong>Сохраним задачу</strong>
                    <p>Данные и параметры появятся в вашем рабочем пространстве.</p>
                </div>
            </div>
            <div class="process-step"><x-icon name="queue" />
                <div><strong>Передадим в очередь</strong>
                    <p>RabbitMQ доставит задание свободному обработчику.</p>
                </div>
            </div>
            <div class="process-step"><x-icon name="check" />
                <div><strong>Подготовим результат</strong>
                    <p>Следите за статусом и историей на странице задачи.</p>
                </div>
            </div>
        </aside>
    </div>
</x-layout>
