<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'taskflow:admin {email} {--name=Администратор}';

    protected $description = 'Создать локального администратора с паролем из скрытого ввода';

    public function handle(): int
    {
        $data = ['email' => mb_strtolower($this->argument('email')), 'name' => $this->option('name'), 'password' => $this->secret('Пароль (не менее 12 символов)')];
        $validator = Validator::make($data, ['email' => 'required|email|max:255|unique:users', 'name' => 'required|string|max:255', 'password' => ['required', Password::min(12)]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = new User($data);
        $user->role = 'admin';
        $user->save();
        $this->info('Администратор создан.');

        return self::SUCCESS;
    }
}
