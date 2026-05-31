<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\BudgetLimitService;
use App\Support\CurrencyExchangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

class MoreController extends Controller
{
    public function index(BudgetLimitService $budgetLimitService): View
    {
        return view('more', [
            'user' => auth()->user(),
            'accounts' => Account::where('user_id', auth()->id())->orderBy('type')->orderBy('name')->get(),
            'budgetLimit' => $budgetLimitService->monthlySummary(auth()->user()),
            'budgetWarning' => $budgetLimitService->warningMessage(auth()->user()),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
        ]);

        auth()->user()->update($data);

        return back()->with('status', 'Профіль оновлено.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth()->user();
        $directory = public_path('images/avatars');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $file = $data['avatar'];
        $filename = $user->id . '-' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        if ($user->avatar_path && str_starts_with($user->avatar_path, 'images/avatars/')) {
            File::delete(public_path($user->avatar_path));
        }

        $user->update([
            'avatar_path' => 'images/avatars/' . $filename,
        ]);

        return back()->with('status', 'Фото профілю оновлено.');
    }

    public function updatePreferences(
        Request $request,
        BudgetLimitService $budgetLimitService,
        CurrencyExchangeService $currencyExchangeService
    ): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
            'currency' => ['required', 'in:UAH,USD,EUR,PLN'],
            'monthly_budget' => ['required', 'numeric', 'min:0'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $data['notifications_enabled'] = $request->boolean('notifications_enabled');
        $user = auth()->user();

        try {
            DB::transaction(function () use ($user, &$data, $currencyExchangeService) {
                $data['monthly_budget'] = $currencyExchangeService->convertUserMoney(
                    $user,
                    $data['currency'],
                    (float) $data['monthly_budget']
                );

                $user->update($data);
            });
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors(['currency' => $exception->getMessage()]);
        }

        $warning = $budgetLimitService->warningMessage(auth()->user());

        if ($warning) {
            session()->flash('budget_warning', $warning);
        }

        return back()->with('status', 'Налаштування збережено.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Пароль змінено.');
    }
}
