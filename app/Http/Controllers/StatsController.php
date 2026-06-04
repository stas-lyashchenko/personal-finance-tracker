<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Operation;
use App\Support\BudgetLimitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(Request $request, BudgetLimitService $budgetLimitService): View
    {
        $operations = Operation::with('category')
            ->where('user_id', auth()->id())
            ->orderBy('date')
            ->get();
        $balance = (float) Account::where('user_id', auth()->id())->sum('balance');
        [$customStart, $customEnd] = $this->customPeriod($request);
        $statsData = [
            'week' => $this->periodData($operations, $balance, Carbon::now()->startOfWeek(), Carbon::now()),
            'month' => $this->periodData($operations, $balance, Carbon::now()->startOfMonth(), Carbon::now()),
            'year' => $this->periodData($operations, $balance, Carbon::now()->startOfYear(), Carbon::now()),
        ];

        if ($customStart && $customEnd) {
            $statsData['custom'] = $this->periodData($operations, $balance, $customStart, $customEnd);
        }

        return view('stats', [
            'statsData' => $statsData,
            'selectedPeriod' => isset($statsData['custom']) ? 'custom' : 'month',
            'dateFrom' => $customStart?->toDateString(),
            'dateTo' => $customEnd?->toDateString(),
            'budgetLimit' => $budgetLimitService->monthlySummary(auth()->user()),
            'budgetWarning' => $budgetLimitService->warningMessage(auth()->user()),
        ]);
    }

    private function customPeriod(Request $request): array
    {
        $from = $request->input('date_from');
        $to = $request->input('date_to');

        if (!$from || !$to) {
            return [null, null];
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            return [null, null];
        }

        return $start->gt($end) ? [$end->copy()->startOfDay(), $start->copy()->endOfDay()] : [$start, $end];
    }

    private function periodData($operations, float $balance, Carbon $start, Carbon $end): array
    {
        $periodOperations = $operations->filter(fn($operation) => Carbon::parse($operation->date)->betweenIncluded($start, $end));
        $income = (float) $periodOperations->where('type', 'income')->sum('amount');
        $expense = (float) $periodOperations->where('type', 'expense')->sum('amount');
        $net = $income - $expense;
        $days = max(1, (int) $start->diffInDays($end) + 1);
        $previousStart = $start->copy()->subDays($days);
        $previousEnd = $start->copy()->subSecond();
        $previousOperations = $operations->filter(fn($operation) => Carbon::parse($operation->date)->betweenIncluded($previousStart, $previousEnd));
        $previousIncome = (float) $previousOperations->where('type', 'income')->sum('amount');
        $previousExpense = (float) $previousOperations->where('type', 'expense')->sum('amount');
        $expenseGroups = $periodOperations->where('type', 'expense')
            ->groupBy(fn($operation) => $operation->category->name ?? 'Без категорії')
            ->map(fn($items) => round((float) $items->sum('amount'), 2))
            ->filter(fn($total) => $total > 0)
            ->sortDesc();
        $largestOperation = $periodOperations->where('type', 'expense')->sortByDesc('amount')->first();

        return [
            'balance' => round($balance, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'avgDailyExpense' => round($expense / $days, 2),
            'savingsRate' => $income > 0 ? round(($net / $income) * 100, 1) : 0,
            'topCategory' => $expenseGroups->keys()->first() ?? 'Немає даних',
            'topCategoryValue' => $expenseGroups->first() ?? 0,
            'largestOperation' => $largestOperation?->name ?? 'Немає даних',
            'largestOperationValue' => round((float) ($largestOperation?->amount ?? 0), 2),
            'incomeChange' => $this->percentChange($previousIncome, $income),
            'expenseChange' => $this->percentChange($previousExpense, $expense),
            'categoryLabels' => $expenseGroups
                ->keys()
                ->take(7)
                ->values(),
            'categoryValues' => $expenseGroups
                ->take(7)
                ->values(),
            'flowLabels' => ['Доходи', 'Витрати'],
            'flowValues' => [round($income, 2), round($expense, 2)],
            'trendLabels' => $this->trendLabels($start, $end),
            'trendValues' => $this->trendValues($periodOperations, $start, $end),
        ];
    }

    private function percentChange(float $previous, float $current): float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function trendLabels(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $cursor = $start->copy();
        $step = $start->diffInDays($end) > 40 ? 'month' : 'day';

        while ($cursor->lte($end)) {
            $labels[] = $step === 'month' ? $cursor->format('m.Y') : $cursor->format('d.m');
            $step === 'month' ? $cursor->addMonth() : $cursor->addDay();
        }

        return $labels;
    }

    private function trendValues($operations, Carbon $start, Carbon $end): array
    {
        $step = $start->diffInDays($end) > 40 ? 'month' : 'day';

        $runningTotal = 0;

        return collect($this->trendLabels($start, $end))->map(function ($label) use ($operations, $step, &$runningTotal) {
            $runningTotal += (float) $operations
                ->filter(function ($operation) use ($label, $step) {
                    $date = Carbon::parse($operation->date);
                    return $label === ($step === 'month' ? $date->format('m.Y') : $date->format('d.m'));
                })
                ->sum(fn($operation) => $operation->type === 'income' ? (float) $operation->amount : -(float) $operation->amount);

            return round($runningTotal, 2);
        })->all();
    }
}
