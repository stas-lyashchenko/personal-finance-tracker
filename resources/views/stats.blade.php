<!DOCTYPE html>
<html lang="uk">

<head>
    <meta charset="UTF-8" />
    <title>Personal Finance Tracker | Статистика</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/stats.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

@php
    $currencyCode = $currencyCode ?? (auth()->user()->currency ?? 'UAH');
    $currencySymbol =
        $currencySymbol ??
        match ($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            'PLN' => 'zł',
            default => '₴',
        };
@endphp

<body class="{{ (auth()->user()->theme ?? 'light') === 'dark' ? 'dark-theme' : '' }}">
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
                <span class="ico"><img src="{{ asset('images/icons/option/file/file-blue.png') }}"></span>
                <span class="txt">Операції</span>
            </a>
            <a class="menu-item {{ request()->routeIs('stats') ? 'open' : '' }}" href="{{ route('stats') }}">
                <span class="ico"><img
                        src="{{ asset('images/icons/option/histogram/histogram-white.png') }}"></span>
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
            <div class="topbar-title">Personal Finance Tracker | <span class="topbar-smallTitle"
                    id="pageTitle">Статистика</span></div>
        </div>
    </header>

    <main class="main">
        <div class="page-head">
            <div>
                <h2>Статистика</h2>
                <p class="muted">Аналітика фінансів</p>
            </div>

            <div class="segmented" id="statsPeriodTabs">
                <button class="seg" data-period="week">Тиждень</button>
                <button class="seg active-seg" data-period="month">Місяць</button>
                <button class="seg" data-period="year">Рік</button>
                @if ($statsData['custom'] ?? null)
                    <button class="seg" data-period="custom">Період</button>
                @endif
            </div>
        </div>

        @if ($budgetWarning)
            <div class="form-alert budget-alert">{{ $budgetWarning }}</div>
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

        <form method="GET" action="{{ route('stats') }}" class="date-filter">
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

        <div class="grid3">
            <div class="cards stat blue-card">
                <div class="stat-title">Баланс</div>
                <div class="stat-value" id="statBalance">{{ $currencySymbol }} 0</div>
            </div>
            <div class="cards stat green-card">
                <div class="stat-title">Доходи</div>
                <div class="stat-value" id="statIncome">{{ $currencySymbol }} 0</div>
                <div class="stat-sub" id="statIncomeSub">0% до попереднього періоду</div>
            </div>
            <div class="cards stat red-card">
                <div class="stat-title">Витрати</div>
                <div class="stat-value" id="statExpense">{{ $currencySymbol }} 0</div>
                <div class="stat-sub" id="statExpenseSub">0% до попереднього періоду</div>
            </div>
        </div>

        <div class="stats-insights">
            <div class="insight-card">
                <span>Середні витрати на день</span>
                <strong id="statAvgExpense">{{ $currencySymbol }} 0</strong>
            </div>
            <div class="insight-card">
                <span>Рівень заощаджень</span>
                <strong id="statSavingsRate">0%</strong>
            </div>
            <div class="insight-card">
                <span>Найбільша категорія</span>
                <strong id="statTopCategory">Немає даних</strong>
            </div>
        </div>

        <div class="grid">
            <div class="card-s stat-panel">
                <div class="chart-head">
                    <div>
                        <h3>Витрати за категоріями</h3>
                        <p class="muted">Топ категорій за обраний період</p>
                    </div>
                </div>
                <div class="chart-box compact-chart"><canvas id="categoryChart" height="220"></canvas></div>
            </div>

            <div class="card-s stat-panel">
                <div class="chart-head">
                    <div>
                        <h3>Доходи vs Витрати</h3>
                        <p class="muted">Структура грошового потоку</p>
                    </div>
                </div>
                <div class="chart-box compact-chart"><canvas id="flowChart" height="220"></canvas></div>
            </div>
        </div>
    </main>

    <script>
        window.statsData = @json($statsData);
        window.selectedStatsPeriod = @json($selectedPeriod);
        window.currencySymbol = @json($currencySymbol);
    </script>
    <script src="{{ asset('js/stats.js') }}?v=hide-balance-sub-20260524" defer></script>
</body>

</html>
