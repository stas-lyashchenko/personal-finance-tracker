<?php

namespace App\Support;

use App\Models\Operation;
use App\Models\User;
use Carbon\Carbon;

class BudgetLimitService
{
    public function monthlySummary(User $user): array
    {
        $limit = (float) ($user->monthly_budget ?? 0);
        $spent = (float) Operation::where('user_id', $user->id)
            ->where('type', 'expense')
            ->whereBetween('date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('amount');
        $remaining = $limit > 0 ? $limit - $spent : 0;
        $percent = $limit > 0 ? min(999, round(($spent / $limit) * 100, 1)) : 0;

        return [
            'limit' => round($limit, 2),
            'spent' => round($spent, 2),
            'remaining' => round($remaining, 2),
            'percent' => $percent,
            'enabled' => $limit > 0,
            'exceeded' => $limit > 0 && $spent > $limit,
        ];
    }

    public function warningMessage(User $user): ?string
    {
        if (!$user->notifications_enabled) {
            return null;
        }

        $summary = $this->monthlySummary($user);

        if (!$summary['exceeded']) {
            return null;
        }

        $over = number_format(abs($summary['remaining']), 2, ',', ' ');
        $spent = number_format($summary['spent'], 2, ',', ' ');
        $limit = number_format($summary['limit'], 2, ',', ' ');
        $currency = $user->currency ?? 'UAH';

        return "Увага: місячний ліміт витрат перевищено на {$over} {$currency}. Витрачено {$spent} {$currency} із {$limit} {$currency}.";
    }
}
