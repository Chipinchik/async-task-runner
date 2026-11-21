<template x-if="task.result_data">
    <div class="result-summary">
        <div class="result-success"><x-icon name="check" />
            <div><strong>Обработка завершена</strong><small>Результат сохранён в PostgreSQL</small></div>
        </div>
        <template x-if="task.type === 'demo_delay'">
            <div class="result-metrics">
                <div class="result-metric"><span>Запрошено</span><strong
                        x-text="task.result_data.requested_seconds + ' с'"></strong></div>
                <div class="result-metric"><span>Фактически прошло</span><strong
                        x-text="task.result_data.actual_seconds + ' с'"></strong></div>
            </div>
        </template>
        <template x-if="task.type === 'text_analysis'">
            <div>
                <div class="result-metrics"><template
                        x-for="[key,label] in [['characters','Символов'],['words','Слов'],['lines','Строк'],['unique_words','Уникальных слов']]"
                        :key="key">
                        <div class="result-metric"><span x-text="label"></span><strong
                                x-text="task.result_data[key]"></strong></div>
                    </template></div>
                <h3>Частые слова</h3><template x-for="word in task.result_data.top_words" :key="word.word">
                    <div class="word-row"><span x-text="word.word"></span>
                        <div class="progress-track" aria-hidden="true">
                            <div :style="'transform:scaleX(' + word.count / task.result_data.top_words[0].count + ')'">
                            </div>
                        </div><strong x-text="word.count"></strong>
                    </div>
                </template>
            </div>
        </template>
        <template x-if="task.type === 'csv_analysis'">
            <div>
                <dl class="details">
                    <dt>Записей</dt>
                    <dd x-text="task.result_data.rows"></dd>
                    <dt>Столбцов</dt>
                    <dd x-text="task.result_data.column_count"></dd>
                    <dt>Пустых значений</dt>
                    <dd x-text="task.result_data.empty_values"></dd>
                </dl>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Статистика столбцов CSV">
                    <table>
                        <thead>
                            <tr>
                                <th>Столбец</th>
                                <th>Пусто</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Сумма</th>
                                <th>Среднее</th>
                            </tr>
                        </thead>
                        <tbody><template x-for="column in task.result_data.columns" :key="column.name">
                                <tr>
                                    <td x-text="column.name"></td>
                                    <td x-text="column.empty"></td>
                                    <td x-text="column.min ?? '—'"></td>
                                    <td x-text="column.max ?? '—'"></td>
                                    <td x-text="column.sum ?? '—'"></td>
                                    <td x-text="column.avg ?? '—'"></td>
                                </tr>
                            </template></tbody>
                    </table>
                </div>
                <p class="help">Статистика по непустым значениям полностью числовых столбцов. Таблицу можно прокрутить
                    горизонтально.</p>
            </div>
        </template>
        <details class="section-gap">
            <summary>Посмотреть JSON результата</summary>
            <pre x-text="JSON.stringify(task.result_data,null,2)"></pre>
        </details>
    </div>
</template>
