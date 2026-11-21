<?php

namespace App\Services;

use App\Exceptions\TaskProcessingException;
use App\Models\Task;
use Illuminate\Support\Facades\Storage;

class TaskProcessor
{
    public function process(Task $task, callable $progress): array
    {
        return match ($task->type) {
            'demo_delay' => $this->delay((int) $task->input_data['seconds'], $progress),
            'text_analysis' => $this->text($task->input_data['text']),
            'csv_analysis' => $this->csv(Storage::disk('local')->path($task->input_data['path']), $task->input_data['delimiter'] ?? ',', $progress),
            default => throw new TaskProcessingException('Неизвестный тип задачи.'),
        };
    }

    public function delay(int $seconds, callable $progress): array
    {
        $start = hrtime(true);
        for ($i = 1; $i <= $seconds; $i++) {
            usleep(1000000);
            $progress((int) floor($i / $seconds * 100), "Прошло {$i} из {$seconds} с.");
        }

        return ['requested_seconds' => $seconds, 'actual_seconds' => round((hrtime(true) - $start) / 1e9, 3)];
    }

    public function text(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[’\x{0027}-][\p{L}\p{N}]+)*/u', mb_strtolower($text, 'UTF-8'), $matches);
        $frequencies = array_count_values($matches[0]);
        arsort($frequencies);
        $top = [];
        foreach (array_slice($frequencies, 0, 10, true) as $word => $count) {
            $top[] = ['word' => (string) $word, 'count' => $count];
        }

        return ['characters' => mb_strlen($text, 'UTF-8'), 'words' => count($matches[0]), 'lines' => count(preg_split('/\r\n|\r|\n/u', $text)), 'unique_words' => count($frequencies), 'top_words' => $top];
    }

    public function csv(string $path, string $delimiter, callable $progress): array
    {
        $stream = @fopen($path, 'rb');
        if (! $stream) {
            throw new TaskProcessingException('Файл CSV недоступен в общем хранилище.');
        }
        try {
            $header = fgetcsv($stream, 0, $delimiter, '"', '');
            if (! $header) {
                throw new TaskProcessingException('CSV должен содержать заголовок.');
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '');
            $header = array_map(fn ($value) => trim((string) $value), $header);
            if (count($header) > 100 || in_array('', $header, true) || count(array_unique($header)) !== count($header) || ! mb_check_encoding(implode('', $header), 'UTF-8')) {
                throw new TaskProcessingException('Нужны уникальные непустые заголовки UTF-8, не более 100 столбцов.');
            }
            $columns = array_map(fn ($name) => ['name' => $name, 'empty' => 0, 'numeric_count' => 0, 'is_numeric' => true, 'min' => null, 'max' => null, 'sum' => 0.0], $header);
            $rows = 0;
            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                if (count($row) !== count($header)) {
                    throw new TaskProcessingException('Несовпадение числа столбцов в записи '.($rows + 2).'.');
                }
                $rows++;
                foreach ($row as $index => $value) {
                    $value = trim((string) $value);
                    if (! mb_check_encoding($value, 'UTF-8')) {
                        throw new TaskProcessingException('CSV должен быть в кодировке UTF-8.');
                    }
                    if ($value === '') {
                        $columns[$index]['empty']++;

                        continue;
                    }
                    if (! is_numeric($value) || ! is_finite((float) $value)) {
                        $columns[$index]['is_numeric'] = false;

                        continue;
                    }
                    $number = (float) $value;
                    $columns[$index]['numeric_count']++;
                    $columns[$index]['min'] = min($columns[$index]['min'] ?? $number, $number);
                    $columns[$index]['max'] = max($columns[$index]['max'] ?? $number, $number);
                    $columns[$index]['sum'] += $number;
                    if (! is_finite($columns[$index]['sum'])) {
                        throw new TaskProcessingException('Числовая сумма выходит за допустимый диапазон.');
                    }
                }
                if ($rows % 5000 === 0) {
                    $progress(null, "Прочитано записей: {$rows}.");
                }
            }
            foreach ($columns as &$column) {
                $column['is_numeric'] = $column['is_numeric'] && $column['numeric_count'] > 0;
                $column['avg'] = $column['is_numeric'] ? $column['sum'] / $column['numeric_count'] : null;
                if (! $column['is_numeric']) {
                    $column['min'] = $column['max'] = $column['sum'] = null;
                }
            }

            return ['rows' => $rows, 'column_count' => count($header), 'headers' => $header, 'empty_values' => array_sum(array_column($columns, 'empty')), 'columns' => $columns];
        } finally {
            fclose($stream);
        }
    }
}
