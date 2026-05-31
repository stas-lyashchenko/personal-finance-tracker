<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CurrencyExchangeService
{
    private const CACHE_KEY = 'nbu_currency_rates_to_uah_v2';

    private const LAST_SUCCESSFUL_CACHE_KEY = 'nbu_currency_rates_to_uah_last_successful';

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

        if (!isset($rates[$fromCurrency], $rates[$targetCurrency])) {
            throw new RuntimeException('Немає реального курсу для обраної валюти.');
        }

        $amountInUah = $amount * $rates[$fromCurrency];
        $converted = $amountInUah / $rates[$targetCurrency];

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
            $rates = $this->fetchRatesFromNbu();

            if ($rates) {
                Cache::store('file')->put(self::LAST_SUCCESSFUL_CACHE_KEY, $rates, now()->addDays(30));

                return $rates;
            }

            $lastSuccessfulRates = Cache::store('file')->get(self::LAST_SUCCESSFUL_CACHE_KEY);

            if (is_array($lastSuccessfulRates)) {
                return $lastSuccessfulRates;
            }

            throw new RuntimeException('Не вдалося отримати актуальні курси валют НБУ.');
        });
    }

    private function fetchRatesFromNbu(): ?array
    {
        try {
            $response = $this->requestNbuRates();
        } catch (Throwable $exception) {
            if (!app()->isLocal()) {
                report($exception);

                return null;
            }

            try {
                $response = $this->requestNbuRates(withoutVerifying: true);
            } catch (Throwable) {
                return null;
            }
        }

        if (!$response->successful() && app()->isLocal()) {
            try {
                $response = $this->requestNbuRates(withoutVerifying: true);
            } catch (Throwable) {
                return null;
            }
        }

        if (!$response->successful()) {
            return null;
        }

        $rates = ['UAH' => 1.0];

        foreach ($response->json() as $rate) {
            $code = strtoupper($rate['cc'] ?? '');

            if (in_array($code, self::SUPPORTED_CURRENCIES, true)) {
                $rates[$code] = (float) $rate['rate'];
            }
        }

        foreach (self::SUPPORTED_CURRENCIES as $currency) {
            if (!isset($rates[$currency])) {
                return null;
            }
        }

        return $rates;
    }

    private function requestNbuRates(bool $withoutVerifying = false)
    {
        $request = Http::timeout(5);
        $caBundle = config('currency.nbu.ca_bundle');

        if ($caBundle && !$withoutVerifying) {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        if ($withoutVerifying) {
            $request = $request->withoutVerifying();
        }

        return $request->get(config('currency.nbu.exchange_url'));
    }

}
