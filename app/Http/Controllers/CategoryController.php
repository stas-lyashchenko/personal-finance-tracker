<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Category;
use App\Models\Operation;
use Carbon\Carbon;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('user_id', auth()->id())->get();
        [$periodStart, $periodEnd] = $this->selectedPeriod(request('date_from'), request('date_to'));
        $operationTotals = Operation::where('user_id', auth()->id())
            ->selectRaw('category_id, type, SUM(amount) as total')
            ->whereNotNull('category_id')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->groupBy('category_id', 'type')
            ->get()
            ->keyBy(fn($total) => $total->category_id . ':' . $total->type);

        $categories->each(function (Category $category) use ($operationTotals) {
            $category->amount = (float) ($operationTotals->get($category->id . ':' . $category->type)->total ?? 0);
        });
        $iconPath = public_path('images/icons');

        $icons = collect(File::directories($iconPath))
            ->filter(fn($dir) => basename($dir) !== 'option') // пропускаємо папку "option"
            ->mapWithKeys(function ($dir) {
                $category = basename($dir);

                // перетворюємо у масив рядків
                $files = collect(File::files($dir))
                    ->map(fn($file) => $category . '/' . $file->getFilename())
                    ->toArray();

                return [$category => $files];
            })->toArray(); // важливо — щоб Blade міг використовувати [0]

        $monthlyExpenses = Operation::where('user_id', auth()->id())
            ->where('type', 'expense')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->sum('amount');
        $monthlyIncome = Operation::where('user_id', auth()->id())
            ->where('type', 'income')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->sum('amount');
        $dateFrom = $periodStart->toDateString();
        $dateTo = $periodEnd->toDateString();

        return view('categories', compact('categories', 'icons', 'monthlyExpenses', 'monthlyIncome', 'dateFrom', 'dateTo'));
    }

    private function selectedPeriod(?string $from, ?string $to): array
    {
        if (!$from || !$to) {
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfDay()];
        }

        try {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfDay()];
        }

        return $start->gt($end) ? [$end->copy()->startOfDay(), $start->copy()->endOfDay()] : [$start, $end];
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:expense,income',
            'amount' => 'required|numeric|min:0',
            'icon' => 'required|string',
            'color' => 'required|string'
        ]);

        Category::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'type' => $request->type,
            'amount' => $request->amount,
            'icon' => $request->icon,
            'color' => $request->color,
        ]);

        return back();
    }

    public function update(Request $request, $id)
    {
        $category = Category::where('user_id', auth()->id())->findOrFail($id);
        $category->name = $request->name;
        $category->amount = $request->amount;
        $category->icon = $request->icon;
        $category->color = $request->color;
        $category->save();

        return response()->json(['success' => true]);
    }

    public function destroy(Category $category)
    {
        abort_unless((int) $category->user_id === auth()->id(), 404);

        $category->delete();
        return response()->json(['success' => true]);
    }
}
