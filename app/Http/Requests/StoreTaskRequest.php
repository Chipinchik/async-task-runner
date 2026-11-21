<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(Task::TYPES))],
            'seconds' => ['exclude_unless:type,demo_delay', 'required', 'integer', 'between:3,30'],
            'text' => ['exclude_unless:type,text_analysis', 'required', 'string', 'max:100000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! mb_check_encoding($value, 'UTF-8')) {
                    $fail('Текст должен быть в UTF-8.');
                }
            }],
            'csv' => ['exclude_unless:type,csv_analysis', 'required', 'file', 'max:5120', 'extensions:csv,txt', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
            'delimiter' => ['exclude_unless:type,csv_analysis', 'required', Rule::in([',', ';', "\t"])],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Заполните поле :attribute.', 'seconds.between' => 'Задержка должна составлять от 3 до 30 секунд.', 'text.max' => 'Максимум 100 000 символов.', 'csv.max' => 'Максимальный размер CSV — 5 МБ.', 'csv.mimetypes' => 'Загрузите текстовый CSV-файл.', 'csv.extensions' => 'Допустимы файлы .csv и .txt.'];
    }
}
