<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <title>Вхід | Personal Finance Tracker</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <h1>Фінансовий трекер</h1>
            <p>Контролюйте витрати, доходи, рахунки та бюджетні ліміти в одному зручному кабінеті.</p>
        </section>

        <section class="auth-card">
            <div class="auth-card-head">
                <span class="modal-eyebrow">Авторизація</span>
                <h2>Вхід</h2>
                <p>Вкажіть email і пароль свого профілю.</p>
            </div>

            @if ($errors->any())
                <div class="form-alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="edit-form">
                @csrf

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                        placeholder="you@example.com" autocomplete="email" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" placeholder="Ваш пароль"
                        autocomplete="current-password" required>
                </div>

                <label class="check-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>Запам'ятати мене</span>
                </label>

                <button type="submit" class="auth-submit">Увійти</button>
            </form>

            <div class="auth-switch">
                Немає акаунта?
                <a href="{{ route('register') }}">Зареєструватися</a>
            </div>
        </section>
    </main>
</body>

</html>
