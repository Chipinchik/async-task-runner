<x-layout title="Профиль">
    <x-page-heading title="Ваш профиль" description="Личные данные и доступ к рабочему пространству." />
    <div class="settings-grid">
        <section>
            <h2>Основная информация</h2>
            <p class="muted">Имя отображается в меню.<br>Email используется для входа.</p>
        </section>
        <section class="panel padded">
            <div class="profile-identity"><span class="avatar large">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <div>
                    <h2>{{ auth()->user()->name }}</h2><span
                        class="badge neutral">{{ auth()->user()->role === 'admin' ? 'Администратор' : 'Пользователь' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('profile') }}" class="form-stack" x-data="submitForm">@csrf
                @method('PATCH')
                <x-field name="name" label="Имя" :value="auth()->user()->name" required maxlength="100" autocomplete="name" />
                <x-field name="email" label="Email" type="email" :value="auth()->user()->email" required autocomplete="email" />
                <div class="form-actions"><x-submit label="Сохранить изменения" icon="check" /></div>
            </form>
        </section>
    </div>
</x-layout>
