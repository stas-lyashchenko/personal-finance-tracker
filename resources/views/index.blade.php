<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <title>Personal Finance Tracker</title>
    <link rel="stylesheet" href="css/style.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

@php
    $currencyCode = $currencyCode ?? (auth()->user()->currency ?? 'UAH');
    $currencySymbol = $currencySymbol ?? match ($currencyCode) {
        'USD' => '$',
        'EUR' => '€',
        'PLN' => 'zł',
        default => '₴',
    };
@endphp

<body class="account-page {{ (auth()->user()->theme ?? 'light') === 'dark' ? 'dark-theme' : '' }}">
    <aside class="sidebar" id="sidebar">
        <nav class="menu">
            <a class="menu-item {{ request()->routeIs('index') ? 'open' : '' }}" href="{{ route('index') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/wallet/wallet-white.png') }}"></span>
                <span class="txt">Рахунок</span>
            </a>

            <a class="menu-item {{ request()->routeIs('categories') ? 'open' : '' }}" href="{{ route('categories') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/category/category-blue.png') }}"></span>
                <span class="txt">Категорії</span>
            </a>

            <a class="menu-item {{ request()->routeIs('operations') ? 'open' : '' }}" href="{{ route('operations') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/file/file-blue.png') }}"></span>
                <span class="txt">Операції</span>
            </a>

            <a class="menu-item {{ request()->routeIs('stats') ? 'open' : '' }}" href="{{ route('stats') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/histogram/histogram-blue.png') }}"></span>
                <span class="txt">Статистика</span>
            </a>

            <a class="menu-item {{ request()->routeIs('more') ? 'open' : '' }}" href="{{ route('more') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/all/all-blue.png') }}"></span>
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
            <div class="topbar-title">
                Personal Finance Tracker | <span class="topbar-smallTitle" id="pageTitle">Рахунок</span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="card main-balance">
            <div>
                <div class="title">Загальний баланс</div>
                <span class="currency">{{ $currencySymbol }}</span>
                <span class="big">{{ number_format($totalBalance, 2) }}</span>
            </div>

            <div class="small-cards">
                <div class="small-card">
                    <div>Рахунки:</div>
                    <b>{{ number_format($accountsSum, 2) }} {{ $currencyCode }}</b>
                </div>
                <div class="small-card">
                    <div>Заощадження:</div>
                    <b>{{ number_format($savingsSum, 2) }} {{ $currencyCode }}</b>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div>
                        <span class="modal-eyebrow">Рахунок</span>
                        <h3>Редагувати рахунок</h3>
                    </div>
                    <button type="button" onclick="closeEditModal()" class="close-icon" aria-label="Закрити">×</button>
                </div>

                <form id="editForm" class="edit-form">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="editName">Назва</label>
                        <input type="text" name="name" id="editName" placeholder="Наприклад: Основна карта" required>
                    </div>

                    <div class="form-group">
                        <label for="editBalance">Баланс</label>
                        <input type="number" name="balance" id="editBalance" step="0.01" placeholder="0.00" required>
                    </div>

                    <div class="modal-actions">
                        <button type="button" onclick="closeEditModal()" class="cancel-btn">Скасувати</button>
                        <button type="submit" class="save-btn">Зберегти</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid">
            <div class="card-s account-panel">
                <h3>Рахунки</h3>

                @foreach ($accounts->where('type', 'account') as $acc)
                    <div class="item" data-id="{{ $acc->id }}">
                        <div class="icon {{ $acc->color }}">
                            <img src="{{ asset('images/icons/' . $acc->icon) }}">
                        </div>

                        <div class="item-text">
                            <div class="name">{{ $acc->name }}</div>
                            <div class="amount">{{ $currencySymbol }} {{ number_format($acc->balance, 2) }}</div>
                        </div>
                    </div>
                @endforeach

                <button onclick="toggleForm()" class="add">+ Додати фінансовий рахунок</button>
                <form action="{{ route('accounts.store') }}" method="POST" class="add-form pretty-form" id="addForm"
                    style="display:none;">
                    @csrf
                    <input type="hidden" name="type" value="account">

                    <div class="form-section-title">Новий рахунок</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Назва</label>
                            <input type="text" name="name" placeholder="Основна карта" required>
                        </div>
                        <div class="form-group">
                            <label>Баланс</label>
                            <input type="number" name="balance" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="icon-field">
                    <div class="form-group icon-preview-group">
                        <label>Іконка</label>
                        <div class="icon-select" onclick="toggleIconPicker()">
                            <img id="selectedIconPreview" src="{{ asset('images/icons/card/card.png') }}">
                            <span>Обрати іконку</span>
                        </div>
                    </div>

                    <div class="icon-modal" id="iconModal" style="display:none;">
                        <div class="icon-categories">
                            @foreach ($icons as $category => $items)
                                <div class="category" onclick="showIcons('{{ $category }}')">
                                    <img src="{{ asset('images/icons/' . $items->first()) }}">
                                </div>
                            @endforeach
                        </div>

                        <div class="icon-picker">
                            @foreach ($icons as $category => $items)
                                <div class="icon-group" data-category="{{ $category }}" style="display:none;">
                                    @foreach ($items as $icon)
                                        @php
                                            $filename = pathinfo($icon, PATHINFO_FILENAME);
                                            $parts = explode('-', $filename);
                                            $color = $parts[1] ?? 'gray';
                                        @endphp

                                        <div class="icon-option" data-icon="{{ $icon }}" data-color="{{ $color }}">
                                            <img src="{{ asset('images/icons/' . $icon) }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    </div>

                    <input type="hidden" name="icon" id="selectedIcon" value="card/card.png">
                    <input type="hidden" name="color" id="selectedColor" value="gray">
                    <button type="submit" class="add">Додати рахунок</button>
                </form>

                <h3 class="panel-subtitle">Заощадження</h3>

                @foreach ($accounts->where('type', 'savings') as $acc)
                    <div class="item" data-id="{{ $acc->id }}">
                        <div class="icon {{ $acc->color }}">
                            <img src="{{ asset('images/icons/' . $acc->icon) }}">
                        </div>

                        <div class="item-text">
                            <div class="name">{{ $acc->name }}</div>
                            <div class="amount">{{ $currencySymbol }} {{ number_format($acc->balance, 2) }}</div>
                        </div>
                    </div>
                @endforeach

                <button onclick="toggleSavingsForm()" class="add">+ Додати ощадний рахунок</button>
                <form action="{{ route('accounts.store') }}" method="POST" class="add-form pretty-form"
                    id="addSavingsForm" style="display:none;">
                    @csrf
                    <input type="hidden" name="type" value="savings">

                    <div class="form-section-title">Нові заощадження</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Назва</label>
                            <input type="text" name="name" placeholder="Подушка безпеки" required>
                        </div>
                        <div class="form-group">
                            <label>Баланс</label>
                            <input type="number" name="balance" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="icon-field">
                    <div class="form-group icon-preview-group">
                        <label>Іконка</label>
                        <div class="icon-select" onclick="toggleSavingsIconPicker()">
                            <img id="selectedSavingsIconPreview" src="{{ asset('images/icons/coins/coins-blue.png') }}">
                            <span>Обрати іконку</span>
                        </div>
                    </div>

                    <div class="icon-modal" id="savingsIconModal" style="display:none;">
                        <div class="icon-categories">
                            @foreach ($icons as $category => $items)
                                <div class="category" onclick="showSavingsIcons('{{ $category }}')">
                                    <img src="{{ asset('images/icons/' . $items->first()) }}">
                                </div>
                            @endforeach
                        </div>

                        <div class="icon-picker">
                            @foreach ($icons as $category => $items)
                                <div class="icon-group" data-category="{{ $category }}" style="display:none;">
                                    @foreach ($items as $icon)
                                        @php
                                            $filename = pathinfo($icon, PATHINFO_FILENAME);
                                            $parts = explode('-', $filename);
                                            $color = $parts[1] ?? 'gray';
                                        @endphp

                                        <div class="icon-option-savings" data-icon="{{ $icon }}"
                                            data-color="{{ $color }}">
                                            <img src="{{ asset('images/icons/' . $icon) }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    </div>

                    <input type="hidden" name="icon" id="selectedSavingsIcon" value="coins/coins-blue.png">
                    <input type="hidden" name="color" id="selectedSavingsColor" value="blue">
                    <button type="submit" class="add">Додати</button>
                </form>
            </div>

            <div class="card-add">
                <h3>Динаміка балансу</h3>
                <div class="graf">
                    <canvas id="balanceChart" height="260"></canvas>
                </div>
            </div>
        </div>
    </main>

    <div id="contextMenu" class="context-menu">
        <button onclick="editAccount()">Редагувати</button>
        <button onclick="deleteAccount()">Видалити</button>
    </div>

    <script>
        window.balanceChartData = @json($balanceChart);
        window.currencySymbol = @json($currencySymbol);
    </script>
    <script src="js/index.js" defer></script>
</body>

</html>
