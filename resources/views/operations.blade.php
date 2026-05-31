<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8" />
    <title>Операції</title>
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

<body>
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
            <a class="menu-item {{ request()->routeIs('operations') ? 'open' : '' }}" href="{{ route('operations') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/file/file-white.png') }}"></span>
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
                Personal Finance Tracker | <span class="topbar-smallTitle" id="pageTitle">Операції</span>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="page-head">
            <div>
                <h2>Операції</h2>
                <p class="muted">Історія всіх транзакцій</p>
            </div>
            <button class="primary-btn" onclick="toggleAddOperationModal()">+ Додати операцію</button>
        </div>

        @if (session('budget_warning') || $budgetWarning)
            <div class="form-alert budget-alert">
                {{ session('budget_warning') ?? $budgetWarning }}
            </div>
        @endif

        @if ($budgetLimit['enabled'] ?? false)
            <div class="cardo budget-card {{ $budgetLimit['exceeded'] ?? false ? 'budget-card-danger' : '' }}">
                <div>
                    <div class="budget-title">Місячний ліміт витрат</div>
                    <div class="muted">
                        Витрачено {{ number_format($budgetLimit['spent'], 2, ',', ' ') }} {{ $currencyCode }} із
                        {{ number_format($budgetLimit['limit'], 2, ',', ' ') }} {{ $currencyCode }}
                    </div>
                </div>
                <div class="budget-meter" aria-label="Прогрес ліміту">
                    <span style="width: {{ min(100, $budgetLimit['percent']) }}%"></span>
                </div>
                <div class="budget-balance {{ $budgetLimit['exceeded'] ?? false ? 'neg' : 'pos' }}">
                    {{ $budgetLimit['exceeded'] ?? false ? 'Перевищено' : 'Залишилось' }}
                    {{ number_format(abs($budgetLimit['remaining']), 2, ',', ' ') }} {{ $currencyCode }}
                </div>
            </div>
        @endif

        <div id="addOperationModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div>
                        <span class="modal-eyebrow">Операція</span>
                        <h3>Додати операцію</h3>
                    </div>
                    <button type="button" onclick="toggleAddOperationModal()" class="close-icon"
                        aria-label="Закрити">×</button>
                </div>
                <form method="POST" action="{{ route('operations.store') }}" class="edit-form">
                    @csrf
                    <div class="form-group">
                        <label>Назва</label>
                        <input type="text" name="name" placeholder="Наприклад: Супермаркет" required>
                    </div>
                    <div class="form-group">
                        <label>Категорія</label>
                        <select name="category_id" required>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Рахунок або заощадження</label>
                        <select name="account_id" required>
                            @forelse ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} -
                                    {{ $account->type === 'savings' ? 'заощадження' : 'рахунок' }}</option>
                            @empty
                                <option value="">Спочатку створіть рахунок</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Сума</label>
                            <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00"
                                required>
                        </div>
                        <div class="form-group">
                            <label>Тип</label>
                            <select name="type" required>
                                <option value="expense">Витрати</option>
                                <option value="income">Доходи</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Метод оплати</label>
                        <select name="method" required>
                            <option value="card">Карта</option>
                            <option value="cash">Готівка</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" onclick="toggleAddOperationModal()"
                            class="cancel-btn">Скасувати</button>
                        <button type="submit" class="save-btn" @disabled($accounts->isEmpty() || $categories->isEmpty())>Додати</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="editModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div>
                        <span class="modal-eyebrow">Операція</span>
                        <h3>Редагувати операцію</h3>
                    </div>
                    <button type="button" onclick="closeEditModal()" class="close-icon"
                        aria-label="Закрити">×</button>
                </div>

                <form id="editForm" class="edit-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editId">

                    <div class="form-group">
                        <label for="editName">Назва</label>
                        <input type="text" name="name" id="editName" placeholder="Наприклад: Супермаркет"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="editCategory">Категорія</label>
                        <select name="category_id" id="editCategory" required>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editAccount">Рахунок або заощадження</label>
                        <select name="account_id" id="editAccount" required>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} -
                                    {{ $account->type === 'savings' ? 'заощадження' : 'рахунок' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editAmount">Сума</label>
                            <input type="number" name="amount" id="editAmount" min="0.01" step="0.01"
                                placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label for="editType">Тип</label>
                            <select id="editType" name="type" required>
                                <option value="expense">Витрати</option>
                                <option value="income">Доходи</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editMethod">Метод оплати</label>
                        <select id="editMethod" name="method" required>
                            <option value="card">Карта</option>
                            <option value="cash">Готівка</option>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button type="button" onclick="closeEditModal()" class="cancel-btn">Скасувати</button>
                        <button type="submit" class="save-btn">Зберегти</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="cardo filters">
            <div class="search-row">
                <input id="searchInput" class="search" type="text" placeholder="Пошук операції..." />
            </div>
            <div class="chip-row">
                <button class="chipf active-chip" data-filter="all">Всі операції</button>
                <button class="chip" data-filter="expense">Витрати</button>
                <button class="chip" data-filter="income">Доходи</button>
            </div>
        </div>

        @php
            use Carbon\Carbon;

            $groupedOperations = $operations->groupBy(function ($op) {
                $opDate = Carbon::parse($op->date)->startOfDay();

                if ($opDate->eq(Carbon::today())) {
                    return 'Сьогодні';
                }

                if ($opDate->eq(Carbon::yesterday())) {
                    return 'Вчора';
                }

                return 'Раніше';
            });
        @endphp

        <div id="operationsList">
            @foreach ($groupedOperations as $dateLabel => $ops)
                <div class="date-group">
                    <h3 class="date-label">{{ $dateLabel }}</h3>
                    @foreach ($ops as $op)
                        <div class="op" data-id="{{ $op->id }}" data-name="{{ $op->name }}"
                            data-amount="{{ $op->amount }}" data-category="{{ $op->category_id }}"
                            data-account="{{ $op->account_id }}" data-type="{{ $op->type }}"
                            data-method="{{ $op->method }}">
                            <div class="op-left">
                                <div class="op-ico {{ $op->type === 'income' ? 'green2' : 'gray' }}">
                                    <img src="{{ asset('images/icons/' . ($op->category?->icon ?? 'option/file/file-blue.png')) }}">
                                </div>
                                <div>
                                    <div class="op-name">{{ $op->name }} |
                                        {{ $op->category?->name ?? 'Без категорії' }}</div>
                                    <div class="op-sub">
                                        {{ $op->method == 'card' ? 'Карта' : 'Готівка' }}
                                        @if ($op->account)
                                            · {{ $op->account->name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="op-right">
                                <div class="op-amt {{ $op->type == 'expense' ? 'neg' : 'pos' }}">
                                    {{ $op->type == 'expense' ? '-' : '+' }}
                                    {{ number_format($op->amount, 0, ',', ' ') }}
                                    {{ $currencySymbol }}
                                </div>
                                <div class="op-date">{{ Carbon::parse($op->date)->format('d.m.Y H:i') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </main>

    <div id="contextMenu" class="context-menu">
        <button onclick="editAccount()">Редагувати</button>
        <button onclick="deleteAccount()">Видалити</button>
    </div>

    <script src="js/operations.js" defer></script>
</body>

</html>
