<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <title>Реєстрація | Personal Finance Tracker</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <h1>Фінансовий трекер</h1>
            <p>Створіть профіль, щоб вести облік операцій, переглядати статистику та стежити за лімітами.</p>
        </section>

        <section class="auth-card">
            <div class="auth-card-head">
                <span class="modal-eyebrow">Новий профіль</span>
                <h2>Реєстрація</h2>
                <p>Заповніть дані для створення акаунта.</p>
            </div>

            @if ($errors->any())
                <div class="form-alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="edit-form">
                @csrf

                <div class="form-group">
                    <label for="name">Ім'я</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                        placeholder="Ваше ім'я" autocomplete="name" required autofocus>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                        placeholder="you@example.com" autocomplete="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" placeholder="Мінімум 8 символів"
                        autocomplete="new-password" required>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Підтвердження пароля</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        placeholder="Повторіть пароль" autocomplete="new-password" required>
                </div>

                <button type="submit" class="auth-submit">Створити акаунт</button>
            </form>

            <div class="auth-switch">
                Вже маєте акаунт?
                <a href="{{ route('login') }}">Увійти</a>
            </div>
        </section>
    </main>
</body>

</html>
