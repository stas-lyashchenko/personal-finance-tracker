<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyExchangeService
{
    private const CACHE_KEY = 'nbu_currency_rates_to_uah';

    private const SUPPORTED_CURRENCIES = ['UAH', 'USD', 'EUR', 'PLN'];

    public static function symbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'PLN' => 'zł',
            default => '₴',
        };
    }

    public function convertUserMoney(User $user, string $targetCurrency, float $monthlyBudget): float
    {
        $fromCurrency = strtoupper($user->currency ?? 'UAH');
        $targetCurrency = strtoupper($targetCurrency);

        if ($fromCurrency === $targetCurrency) {
            return round($monthlyBudget, 2);
        }

        $this->convertAccounts($user, $fromCurrency, $targetCurrency);
        $this->convertCategories($user, $fromCurrency, $targetCurrency);
        $this->convertOperations($user, $fromCurrency, $targetCurrency);

        return $this->convert($monthlyBudget, $fromCurrency, $targetCurrency);
    }

    public function convert(float $amount, string $fromCurrency, string $targetCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($fromCurrency === $targetCurrency) {
            return round($amount, 2);
        }

        $rates = $this->ratesToUah();

        $amountInUah = $amount * ($rates[$fromCurrency] ?? 1);
        $converted = $amountInUah / ($rates[$targetCurrency] ?? 1);

        return round($converted, 2);
    }

    private function convertAccounts(User $user, string $fromCurrency, string $targetCurrency): void
    {
        Account::where('user_id', $user->id)->each(function (Account $account) use ($fromCurrency, $targetCurrency) {
            $account->forceFill([
                'balance' => $this->convert((float) $account->balance, $fromCurrency, $targetCurrency),
            ])->save();
        });
    }

    private function convertCategories(User $user, string $fromCurrency, string $targetCurrency): void
    {
        Category::where('user_id', $user->id)->each(function (Category $category) use ($fromCurrency, $targetCurrency) {
            $category->forceFill([
                'amount' => $this->convert((float) $category->amount, $fromCurrency, $targetCurrency),
            ])->save();
        });
    }

    private function convertOperations(User $user, string $fromCurrency, string $targetCurrency): void
    {
        Operation::where('user_id', $user->id)->each(function (Operation $operation) use ($fromCurrency, $targetCurrency) {
            $operation->forceFill([
                'amount' => $this->convert((float) $operation->amount, $fromCurrency, $targetCurrency),
            ])->save();
        });
    }

    private function ratesToUah(): array
    {
        return Cache::store('file')->remember(self::CACHE_KEY, now()->addDay(), function () {
            $rates = ['UAH' => 1.0];

            try {
                $response = Http::timeout(5)->get('https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json');

                if ($response->successful()) {
                    foreach ($response->json() as $rate) {
                        $code = strtoupper($rate['cc'] ?? '');

                        if (in_array($code, self::SUPPORTED_CURRENCIES, true)) {
                            $rates[$code] = (float) $rate['rate'];
                        }
                    }
                }
            } catch (ConnectionException) {
                return $this->fallbackRates();
            }

            return $rates + $this->fallbackRates();
        });
    }

    private function fallbackRates(): array
    {
        return [
            'UAH' => 1.0,
            'USD' => 40.0,
            'EUR' => 43.0,
            'PLN' => 10.0,
        ];
    }
}
