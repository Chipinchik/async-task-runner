<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetAdminPassword extends Command
{
    protected $signature = 'taskflow:admin-password {email}';

    protected $description = 'Изменить пароль существующего администратора через скрытый ввод';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower($this->argument('email')))->where('role', 'admin')->first();
        if (! $user) {
            $this->error('Администратор с таким email не найден.');

            return self::FAILURE;
        }

        $data = [
            'password' => $this->secret('Новый пароль (не менее 12 символов)'),
            'password_confirmation' => $this->secret('Повторите новый пароль'),
        ];
        $validator = Validator::make($data, ['password' => ['required', 'confirmed', Password::min(12)]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $data): void {
            $user->password = $data['password'];
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        $this->info('Пароль изменён. Предыдущие сессии завершены.');

        return self::SUCCESS;
    }
}
