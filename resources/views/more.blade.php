<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8" />
    <title>Personal Finance Tracker | Більше</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/more.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

@php
    $currencyCode = $currencyCode ?? ($user->currency ?? 'UAH');
    $currencySymbol =
        $currencySymbol ??
        match ($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            'PLN' => 'zł',
            default => '₴',
        };
@endphp

<body class="{{ ($user->theme ?? 'light') === 'dark' ? 'dark-theme' : '' }}">
    <aside class="sidebar" id="sidebar">
        <nav class="menu">
            <a class="menu-item {{ request()->routeIs('index') ? 'open' : '' }}" href="{{ route('index') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/wallet/wallet-blue.png') }}"></span>
                <span class="txt">Рахунок</span>
            </a>
            <a class="menu-item {{ request()->routeIs('categories') ? 'open' : '' }}" href="{{ route('categories') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/category/category-blue.png') }}"></span>
                <span class="txt">Категорії</span>
            </a>
            <a class="menu-item {{ request()->routeIs('operations') ? 'open' : '' }}"
                href="{{ route('operations') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/file/file-blue.png') }}"></span>
                <span class="txt">Операції</span>
            </a>
            <a class="menu-item {{ request()->routeIs('stats') ? 'open' : '' }}" href="{{ route('stats') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/histogram/histogram-blue.png') }}"></span>
                <span class="txt">Статистика</span>
            </a>
            <a class="menu-item {{ request()->routeIs('more') ? 'open' : '' }}" href="{{ route('more') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/all/all-white.png') }}"></span>
                <span class="txt">Більше</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="menu-logout">
                @csrf
                <button type="submit" class="menu-item logout-item">
                    <span class="ico">↪</span>
                    <span class="txt">Вийти</span>
                </button>
            </form>
        </nav>
    </aside>

    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Personal Finance Tracker | <span class="topbar-smallTitle"
                    id="pageTitle">Більше</span></div>
        </div>
    </header>

    <main class="main">
        <h2>Більше</h2>
        <p class="muted" style="margin-bottom:14px;">Налаштування та додаткові опції</p>

        @if (session('status'))
            <div class="form-alert success-alert">{{ session('status') }}</div>
        @endif

        @if (session('budget_warning') || $budgetWarning)
            <div class="form-alert budget-alert">
                {{ session('budget_warning') ?? $budgetWarning }}
            </div>
        @endif

        @if ($errors->any())
            <div class="form-alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card profile-card">
            <form method="POST" action="{{ route('more.avatar') }}" enctype="multipart/form-data" id="avatarForm">
                @csrf
                @method('PUT')
                <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp" hidden>
                <button type="button" class="avatar avatar-button" id="avatarButton" aria-label="Змінити фото профілю"
                    title="Змінити фото профілю">
                    <img src="{{ $user->avatar_path ? asset($user->avatar_path) : asset('images/icons/user.png') }}"
                        class="{{ $user->avatar_path ? 'avatar-image' : 'avatar-image avatar-image-default' }}"
                        alt="Фото профілю">
                </button>
            </form>
            <div class="profile-info">
                <div class="profile-name">{{ $user->name }}</div>
                <div class="profile-email">{{ $user->email }}</div>
            </div>
        </div>

        <h3 class="subhead">НАЛАШТУВАННЯ</h3>
        <div class="settings-grid">
            <button type="button" class="card setting" data-modal-target="profileModal">
                <div class="set-ico blue2"><img src="{{ asset('images/icons/user-blue.png') }}"></div>
                <span><b>Профіль</b><span class="muted">Імʼя та email</span></span>
            </button>
            <button type="button" class="card setting" data-modal-target="preferencesModal">
                <div class="set-ico purple2"><img src="{{ asset('images/icons/option/moon.png') }}"></div>
                <span><b>Тема</b><span
                        class="muted">{{ ($user->theme ?? 'light') === 'dark' ? 'Темна' : 'Світла' }}</span></span>
            </button>
            <button type="button" class="card setting" data-modal-target="preferencesModal">
                <div class="set-ico green2"><img src="{{ asset('images/icons/option/dollar.png') }}"></div>
                <span><b>Валюта</b><span class="muted">{{ $user->currency ?? 'UAH' }}</span></span>
            </button>
            <button type="button" class="card setting" data-modal-target="preferencesModal">
                <div class="set-ico purple2"><img src="{{ asset('images/icons/option/limit.png') }}"></div>
                <span><b>Бюджетні ліміти</b><span class="muted">{{ $currencySymbol }}
                        {{ number_format((float) ($user->monthly_budget ?? 0), 2) }}</span></span>
            </button>
            <button type="button" class="card setting import" onclick="openImportModal()">
                <div class="set-ico orange2"><img src="{{ asset('images/icons/option/export.png') }}"></div>
                <span><b>Імпорт даних</b><span class="muted">CSV або XLSX з банку</span></span>
            </button>
            <button type="button" class="card setting" data-modal-target="preferencesModal">
                <div class="set-ico yellow2"><img src="{{ asset('images/icons/option/bell.png') }}"></div>
                <span><b>Сповіщення</b><span
                        class="muted">{{ $user->notifications_enabled ? 'Увімкнено' : 'Вимкнено' }}</span></span>
            </button>
            <button type="button" class="card setting" data-modal-target="passwordModal">
                <div class="set-ico red2"><img src="{{ asset('images/icons/option/check.png') }}"></div>
                <span><b>Безпека</b><span class="muted">Зміна пароля</span></span>
            </button>
        </div>

        <div id="profileModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div><span class="modal-eyebrow">Профіль</span>
                        <h3>Редагувати профіль</h3>
                    </div>
                    <button type="button" class="close-icon" data-close-modal>×</button>
                </div>
                <form method="POST" action="{{ route('more.profile') }}" class="edit-form">
                    @csrf
                    @method('PUT')
                    <div class="form-group"><label>Імʼя</label><input type="text" name="name"
                            value="{{ old('name', $user->name) }}" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email"
                            value="{{ old('email', $user->email) }}" required></div>
                    <div class="modal-actions"><button type="button" class="cancel-btn"
                            data-close-modal>Скасувати</button><button type="submit"
                            class="save-btn">Зберегти</button></div>
                </form>
            </div>
        </div>

        <div id="preferencesModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div><span class="modal-eyebrow">Налаштування</span>
                        <h3>Параметри додатка</h3>
                    </div>
                    <button type="button" class="close-icon" data-close-modal>×</button>
                </div>
                <form method="POST" action="{{ route('more.preferences') }}" class="edit-form">
                    @csrf
                    @method('PUT')
                    <div class="form-row">
                        <div class="form-group">
                            <label>Тема</label>
                            <select name="theme">
                                <option value="light" @selected(($user->theme ?? 'light') === 'light')>Світла</option>
                                <option value="dark" @selected(($user->theme ?? 'light') === 'dark')>Темна</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Валюта</label>
                            <select name="currency">
                                @foreach (['UAH', 'USD', 'EUR', 'PLN'] as $currency)
                                    <option value="{{ $currency }}" @selected(($user->currency ?? 'UAH') === $currency)>
                                        {{ $currency }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>Місячний бюджет</label><input type="number" name="monthly_budget"
                            min="0" step="0.01"
                            value="{{ old('monthly_budget', $user->monthly_budget ?? 0) }}"></div>
                    <label class="check-row"><input type="checkbox" name="notifications_enabled" value="1"
                            @checked($user->notifications_enabled)> Увімкнути сповіщення</label>
                    <div class="modal-actions"><button type="button" class="cancel-btn"
                            data-close-modal>Скасувати</button><button type="submit"
                            class="save-btn">Зберегти</button></div>
                </form>
            </div>
        </div>

        <div id="passwordModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div><span class="modal-eyebrow">Безпека</span>
                        <h3>Змінити пароль</h3>
                    </div>
                    <button type="button" class="close-icon" data-close-modal>×</button>
                </div>
                <form method="POST" action="{{ route('more.password') }}" class="edit-form">
                    @csrf
                    @method('PUT')
                    <div class="form-group"><label>Поточний пароль</label><input type="password"
                            name="current_password" required></div>
                    <div class="form-group"><label>Новий пароль</label><input type="password" name="password"
                            required></div>
                    <div class="form-group"><label>Повторіть пароль</label><input type="password"
                            name="password_confirmation" required></div>
                    <div class="modal-actions"><button type="button" class="cancel-btn"
                            data-close-modal>Скасувати</button><button type="submit"
                            class="save-btn">Оновити</button></div>
                </form>
            </div>
        </div>

        <div id="importModal" class="modal" style="display:none;">
            <div class="modal-content">
                <h3>Імпорт операцій</h3>
                <form id="importForm" enctype="multipart/form-data">
                    @csrf
                    <label id="label-10">Банк</label>
                    <select name="bank" id="bank">
                        <option value="privat">ПриватБанк</option>
                        <option value="mono">Monobank</option>
                    </select>
                    <label id="label-10">Файл</label>
                    <div class="file-picker">
                        <input type="file" name="file" id="file" accept=".csv,.xlsx,.xls">
                        <label for="file" class="file-picker-button">Обрати файл</label>
                        <span id="fileName" class="file-picker-name" data-empty-text="Файл не вибрано">Файл не
                            вибрано</span>
                    </div>
                    <button type="submit" class="add">Завантажити</button>
                    <button type="button" onclick="closeImportModal()" class="close-btn">Закрити</button>
                </form>
            </div>
        </div>
    </main>

    <script src="js/more.js" defer></script>
</body>

</html>
