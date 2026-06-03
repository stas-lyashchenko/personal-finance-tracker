<?php

namespace App\Http\Controllers;
use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Support\BudgetLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
class OperationController extends Controller
{
    public function index(BudgetLimitService $budgetLimitService)
    {
        $operations = Operation::with(['category', 'account'])
            ->where('user_id', Auth::id())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
        $categories = Category::where('user_id', Auth::id())->orderBy('name')->get();
        $accounts = Account::where('user_id', Auth::id())->orderBy('type')->orderBy('name')->get();
        $budgetLimit = $budgetLimitService->monthlySummary(Auth::user());
        $budgetWarning = $budgetLimitService->warningMessage(Auth::user());
        return view('operations', compact('operations', 'categories', 'accounts', 'budgetLimit', 'budgetWarning'));
    }
    public function store(Request $request, BudgetLimitService $budgetLimitService)
    {
        $data = $request->validate([
            'account_id' => 'required',
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn($query) => $query
                    ->where('user_id', Auth::id())
                    ->where('type', $request->input('type'))
                ),
            ],
            'name' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense',
            'method' => 'required|in:card,cash',
        ]);
        $data['category_id'] = $data['category_id'] ?: null;
        $account = Account::where('user_id', Auth::id())->findOrFail($data['account_id']);
        $category = $this->categoryForOperation($data['category_id'], $data['type']);
        $data['category_id'] = $category->id;
        DB::transaction(function () use ($data, $account) {
            $operation = Operation::create([
                'user_id' => Auth::id(),
                'account_id' => $account->id,
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'amount' => $data['amount'],
                'type' => $data['type'],
                'method' => $data['method'],
                'date' => now()
            ]);

            $this->applyBalanceEffect($operation);
        });

        $redirect = redirect()->back();
        $warning = $budgetLimitService->warningMessage(Auth::user());

        return $warning ? $redirect->with('budget_warning', $warning) : $redirect;
    }

    public function update(Request $request, $id, BudgetLimitService $budgetLimitService)
    {
        $operation = Operation::where('user_id', Auth::id())->findOrFail($id);

        $data = $request->validate([
            'account_id' => 'required',
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn($query) => $query
                    ->where('user_id', Auth::id())
                    ->where('type', $request->input('type'))
                ),
            ],
            'name' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense',
            'method' => 'required|in:card,cash',
        ]);

        $data['category_id'] = $data['category_id'] ?: null;
        Account::where('user_id', Auth::id())->findOrFail($data['account_id']);
        $category = $this->categoryForOperation($data['category_id'], $data['type']);
        $data['category_id'] = $category->id;

        DB::transaction(function () use ($operation, $data) {

            $this->reverseBalanceEffect($operation);

            $operation->update([
                'account_id' => $data['account_id'],
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'amount' => $data['amount'],
                'type' => $data['type'],
                'method' => $data['method'],
            ]);
            $operation->refresh();

            $this->applyBalanceEffect($operation);
        });

        return response()->json([
            'success' => true,
            'budget_warning' => $budgetLimitService->warningMessage(Auth::user()),
        ]);
    }

    public function destroy(Operation $operation)
    {
        abort_unless((int) $operation->user_id === Auth::id(), 404);

        DB::transaction(function () use ($operation) {
            $this->reverseBalanceEffect($operation);
            $operation->delete();
        });

        return response()->json(['success' => true]);
    }

    private function categoryForOperation(?int $categoryId, string $type): Category
    {
        if ($categoryId) {
            return Category::where('user_id', Auth::id())
                ->where('type', $type)
                ->findOrFail($categoryId);
        }

        return Category::firstOrCreate(
            [
                'user_id' => Auth::id(),
                'name' => 'Інше',
                'type' => $type,
            ],
            [
                'amount' => 0,
                'icon' => 'option/category/category-blue.png',
                'color' => 'gray',
            ]
        );
    }

    private function applyBalanceEffect(Operation $operation): void
    {
        $this->moveAccountBalance($operation, $operation->type === 'income' ? 1 : -1);
        $this->moveCategoryAmount($operation, 1);
    }

    private function reverseBalanceEffect(Operation $operation): void
    {
        $this->moveAccountBalance($operation, $operation->type === 'income' ? -1 : 1);
        $this->moveCategoryAmount($operation, -1);
    }

    private function moveAccountBalance(Operation $operation, int $direction): void
    {
        if (!$operation->account_id) {
            return;
        }

        $account = Account::where('user_id', Auth::id())->find($operation->account_id);

        if (!$account) {
            return;
        }

        $account->increment('balance', $direction * (float) $operation->amount);
    }

    private function moveCategoryAmount(Operation $operation, int $direction): void
    {
        if (!$operation->category_id) {
            return;
        }

        $category = Category::where('user_id', Auth::id())->find($operation->category_id);

        if (!$category) {
            return;
        }

        $category->increment('amount', $direction * (float) $operation->amount);
    }
}
