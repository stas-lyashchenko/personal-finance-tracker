<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8">
    <title>Personal Finance Tracker | Категорії</title>
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

<body class="{{ (auth()->user()->theme ?? 'light') === 'dark' ? 'dark-theme' : '' }}">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">
                Personal Finance Tracker | <span class="topbar-smallTitle" id="pageTitle">Категорії</span>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav class="menu">
            <a class="menu-item {{ request()->routeIs('index') ? 'open' : '' }}" href="{{ route('index') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/wallet/wallet-blue.png') }}"></span>
                <span class="txt">Рахунок</span>
            </a>

            <a class="menu-item {{ request()->routeIs('categories') ? 'open' : '' }}" href="{{ route('categories') }}">
                <span class="ico"><img src="{{ asset('images/icons/option/category/category-white.png') }}"></span>
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

    @php
        $expenses = $monthlyExpenses;
        $income = $monthlyIncome;
        $expenseTotal = $categories->where('type', 'expense')->sum('amount');
        $incomeTotal = $categories->where('type', 'income')->sum('amount');
    @endphp

    <main class="main">
        <form method="GET" action="{{ route('categories') }}" class="date-filter">
            <div class="date-filter-row">
                <div class="date-filter-field">
                    <label for="date_from">Від</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}">
                </div>
                <div class="date-filter-field">
                    <label for="date_to">До</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}">
                </div>
                <button type="submit" class="save-btn">Показати</button>
            </div>
        </form>

        <div class="grid">
            <div class="card-c red-card">
                <div class="title">Витрати</div>
                <div class="big">{{ $currencySymbol }} {{ number_format($expenses, 2) }}</div>
                <small>за обраний період</small>
            </div>
            <div class="card-c green-card">
                <div class="title">Доходи</div>
                <div class="big">{{ $currencySymbol }} {{ number_format($income, 2) }}</div>
                <small>за обраний період</small>
            </div>
        </div>

        <div id="editModal" class="modal" style="display:none;">
            <div class="modal-content edit-modal-content">
                <div class="modal-header">
                    <div>
                        <span class="modal-eyebrow">Категорія</span>
                        <h3>Редагувати категорію</h3>
                    </div>
                    <button type="button" onclick="closeEditModal()" class="close-icon" aria-label="Закрити">×</button>
                </div>

                <form id="editForm" class="edit-form">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="id" id="editId">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editName">Назва</label>
                            <input type="text" name="name" id="editName" placeholder="Назва категорії" required>
                        </div>
                        <div class="form-group">
                            <label for="editAmount">Ліміт або сума</label>
                            <input type="number" name="amount" id="editAmount" min="0" step="0.01" placeholder="0.00"
                                required>
                        </div>
                    </div>

                    <div class="icon-field">
                    <div class="form-group icon-preview-group">
                        <label>Іконка</label>
                        <div class="icon-select" onclick="toggleEditIconPicker()">
                            <img id="editIconPreview" src="{{ asset('images/icons/card/card.png') }}">
                            <span>Обрати іконку</span>
                        </div>
                    </div>

                    <div class="icon-modal" id="editIconModal" style="display:none;">
                        <div class="icon-categories">
                            @foreach ($icons as $category => $items)
                                <div class="category" onclick="showEditIcons('{{ $category }}')">
                                    <img src="{{ asset('images/icons/' . $items[0]) }}">
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
                                        <div class="icon-option-edit" data-icon="{{ $icon }}" data-color="{{ $color }}">
                                            <img src="{{ asset('images/icons/' . $icon) }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    </div>

                    <input type="hidden" name="icon" id="editIcon">
                    <input type="hidden" name="color" id="editColor">

                    <div class="modal-actions">
                        <button type="button" onclick="closeEditModal()" class="cancel-btn">Скасувати</button>
                        <button type="submit" class="save-btn">Зберегти</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="grid category-grid">
            <div class="cardc category-panel">
                <h3>Категорії витрат</h3>

                @foreach ($categories->where('type', 'expense') as $cat)
                    @php
                        $percent = $expenseTotal > 0 ? ($cat->amount / $expenseTotal) * 100 : 0;
                    @endphp
                    <div class="cat" data-id="{{ $cat->id }}">
                        <div class="cat-row">
                            <div class="cat-left">
                                <div class="cat-ico {{ $cat->color }}">
                                    <img src="{{ asset('images/icons/' . $cat->icon) }}">
                                </div>
                                <div class="cat-name">{{ $cat->name }}</div>
                            </div>
                            <div class="cat-right">
                                <div class="cat-amount">{{ $currencySymbol }} {{ number_format($cat->amount, 2) }}</div>
                            </div>
                        </div>

                        <div class="cat-progress">
                            <div class="bar">
                                <div style="width: {{ round($percent, 1) }}%"></div>
                            </div>
                            <div class="cat-percent">{{ round($percent) }}%</div>
                            <div class="cat-actions">
                                <button class="icon-btn edit">Редагувати</button>
                                <button class="icon-btn delete">Видалити</button>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button class="add" onclick="toggleExpenseForm()">+ Додати категорію витрат</button>
                <form action="{{ route('categories.store') }}" method="POST" class="add-form pretty-form"
                    id="addExpenseForm" style="display:none;">
                    @csrf
                    <input type="hidden" name="type" value="expense">

                    <div class="form-section-title">Нова категорія витрат</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Назва</label>
                            <input type="text" name="name" placeholder="Продукти" required>
                        </div>
                        <div class="form-group">
                            <label>Ліміт або сума</label>
                            <input type="number" name="amount" min="0" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="icon-field">
                    <div class="form-group icon-preview-group">
                        <label>Іконка</label>
                        <div class="icon-select" onclick="toggleExpenseIconPicker()">
                            <img id="selectedExpenseIconPreview" src="{{ asset('images/icons/bag/bag-black.png') }}">
                            <span>Обрати іконку</span>
                        </div>
                    </div>

                    <div class="icon-modal" id="expenseIconModal" style="display:none;">
                        <div class="icon-categories">
                            @foreach ($icons as $category => $items)
                                <div class="category" onclick="showExpenseIcons('{{ $category }}')">
                                    <img src="{{ asset('images/icons/' . $items[0]) }}">
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
                                        <div class="icon-option-expense" data-icon="{{ $icon }}"
                                            data-color="{{ $color }}">
                                            <img src="{{ asset('images/icons/' . $icon) }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    </div>

                    <input type="hidden" name="icon" id="selectedExpenseIcon" value="shop/shop-gray.png">
                    <input type="hidden" name="color" id="selectedExpenseColor" value="gray">
                    <button type="submit" class="add">Додати</button>
                </form>
            </div>

            <div class="cardc category-panel">
                <h3>Категорії доходів</h3>

                @foreach ($categories->where('type', 'income') as $cat)
                    @php
                        $percent = $incomeTotal > 0 ? ($cat->amount / $incomeTotal) * 100 : 0;
                    @endphp
                    <div class="cat" data-id="{{ $cat->id }}">
                        <div class="cat-row">
                            <div class="cat-left">
                                <div class="cat-ico {{ $cat->color }}">
                                    <img src="{{ asset('images/icons/' . $cat->icon) }}">
                                </div>
                                <div class="cat-name">{{ $cat->name }}</div>
                            </div>
                            <div class="cat-right">
                                <div class="cat-amount">{{ $currencySymbol }} {{ number_format($cat->amount, 2) }}</div>
                            </div>
                        </div>

                        <div class="cat-progress">
                            <div class="bar">
                                <div style="width: {{ round($percent, 1) }}%"></div>
                            </div>
                            <div class="cat-percent">{{ round($percent) }}%</div>
                            <div class="cat-actions">
                                <button class="icon-btn edit">Редагувати</button>
                                <button class="icon-btn delete">Видалити</button>
                            </div>
                        </div>
                    </div>
                @endforeach

                <button class="add" onclick="toggleIncomeForm()">+ Додати категорію доходів</button>
                <form action="{{ route('categories.store') }}" method="POST" class="add-form pretty-form"
                    id="addIncomeForm" style="display:none;">
                    @csrf
                    <input type="hidden" name="type" value="income">

                    <div class="form-section-title">Нова категорія доходів</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Назва</label>
                            <input type="text" name="name" placeholder="Зарплата" required>
                        </div>
                        <div class="form-group">
                            <label>Планова сума</label>
                            <input type="number" name="amount" min="0" step="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="icon-field">
                    <div class="form-group icon-preview-group">
                        <label>Іконка</label>
                        <div class="icon-select" onclick="toggleIncomeIconPicker()">
                            <img id="selectedIncomeIconPreview" src="{{ asset('images/icons/coins/coins-green.png') }}">
                            <span>Обрати іконку</span>
                        </div>
                    </div>

                    <div class="icon-modal" id="incomeIconModal" style="display:none;">
                        <div class="icon-categories">
                            @foreach ($icons as $category => $items)
                                <div class="category" onclick="showIncomeIcons('{{ $category }}')">
                                    <img src="{{ asset('images/icons/' . $items[0]) }}">
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
                                        <div class="icon-option-income" data-icon="{{ $icon }}"
                                            data-color="{{ $color }}">
                                            <img src="{{ asset('images/icons/' . $icon) }}">
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                    </div>

                    <input type="hidden" name="icon" id="selectedIncomeIcon" value="coins/coins-green.png">
                    <input type="hidden" name="color" id="selectedIncomeColor" value="green">
                    <button type="submit" class="add">Додати</button>
                </form>
            </div>
        </div>
    </main>

    <script src="js/categories.js" defer></script>
</body>

</html>
