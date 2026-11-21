<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower((string) $this->input('email'))]);
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(10)]];
    }

    public function messages(): array
    {
        return ['required' => 'Заполните поле :attribute.', 'email.unique' => 'Этот email уже зарегистрирован.', 'email.email' => 'Введите корректный email.', 'password.confirmed' => 'Пароли не совпадают.', 'password.min' => 'Пароль должен содержать не менее 10 символов.'];
    }
}
