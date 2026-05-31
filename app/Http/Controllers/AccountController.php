<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Account;
use App\Models\Operation;
use Carbon\Carbon;

class AccountController extends Controller
{

    public function index()
    {
        $accounts = Account::where('user_id', auth()->id())->get();

        $iconPath = public_path('images/icons');

        $icons = collect(File::directories($iconPath))
            ->filter(fn($dir) => basename($dir) !== 'option') // ❗ пропускаємо
            ->mapWithKeys(function ($dir) {

                $category = basename($dir);

                $files = collect(File::files($dir))
                    ->map(fn($file) => $category . '/' . $file->getFilename());

                return [$category => $files];
            });
        // сума рахунків
        $accountsSum = $accounts->where('type', 'account')->sum('balance');

        // сума заощаджень
        $savingsSum = $accounts->where('type', 'savings')->sum('balance');

        // загальний баланс
        $totalBalance = $accountsSum + $savingsSum;
        $balanceChart = $this->balanceChartData((float) $totalBalance);

        return view('index', compact('accounts', 'icons', 'accountsSum', 'savingsSum', 'totalBalance', 'balanceChart'));
    }

    private function balanceChartData(float $currentBalance): array
    {
        $start = Carbon::now()->subDays(6)->startOfDay();
        $operations = Operation::where('user_id', auth()->id())
            ->where('date', '>=', $start)
            ->orderBy('date')
            ->get();
        $dailyNet = [];

        foreach ($operations as $operation) {
            $key = Carbon::parse($operation->date)->format('Y-m-d');
            $amount = (float) $operation->amount;
            $dailyNet[$key] = ($dailyNet[$key] ?? 0) + ($operation->type === 'income' ? $amount : -$amount);
        }

        $runningBalance = $currentBalance - array_sum($dailyNet);
        $labels = [];
        $values = [];

        for ($date = $start->copy(); $date->lte(Carbon::now()); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $runningBalance += $dailyNet[$key] ?? 0;
            $labels[] = $date->format('d.m');
            $values[] = round($runningBalance, 2);
        }

        return compact('labels', 'values');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'balance' => 'required|numeric',
            'type' => 'required|in:account,savings',
            'icon' => 'required',
            'color' => 'required'
        ]);

        Account::create($data + ['user_id' => auth()->id()]);

        return redirect()->back();
    }

    public function destroy($id)
    {
        Account::where('user_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    public function show($id)
    {
        return Account::where('user_id', auth()->id())->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $acc = Account::where('user_id', auth()->id())->findOrFail($id);

        $acc->update($request->only(['name', 'balance']));

        return response()->json(['success' => true]);
    }
}
