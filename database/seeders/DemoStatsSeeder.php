<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoStatsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if (!$user) {
            return;
        }

        $account = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Основна картка'],
            ['type' => 'card', 'balance' => 0, 'icon' => 'wallet/wallet-blue.png', 'color' => '#2563eb']
        );

        $categories = collect([
            ['name' => 'Зарплата', 'type' => 'income', 'icon' => 'coins/coins-green.png', 'color' => '#16a34a'],
            ['name' => 'Фриланс', 'type' => 'income', 'icon' => 'bag/bag-green.png', 'color' => '#14b8a6'],
            ['name' => 'Продукти', 'type' => 'expense', 'icon' => 'shop/shop-orange.png', 'color' => '#f97316'],
            ['name' => 'Транспорт', 'type' => 'expense', 'icon' => 'car/car-purple.png', 'color' => '#2563eb'],
            ['name' => 'Кафе', 'type' => 'expense', 'icon' => 'cafe/cafe-gray.png', 'color' => '#ef4444'],
            ['name' => 'Комунальні', 'type' => 'expense', 'icon' => 'house/house-gray.png', 'color' => '#8b5cf6'],
            ['name' => 'Підписки', 'type' => 'expense', 'icon' => 'stats/stats-green.png', 'color' => '#0ea5e9'],
            ['name' => 'Здоровʼя', 'type' => 'expense', 'icon' => 'pills/pills.png', 'color' => '#22c55e'],
        ])->mapWithKeys(function (array $category) use ($user) {
            $model = Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $category['name']],
                ['type' => $category['type'], 'amount' => 0, 'icon' => $category['icon'], 'color' => $category['color']]
            );

            return [$category['name'] => $model];
        });

        if (Operation::where('user_id', $user->id)->where('name', 'like', 'Демо:%')->exists()) {
            return;
        }

        $operations = [];
        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $month = Carbon::now()->subMonths($monthOffset)->startOfMonth();
            $operations[] = ['Зарплата', 'Демо: зарплата', 43000 + (5 - $monthOffset) * 900, 'income', $month->copy()->day(5)->hour(9)];
            $operations[] = ['Фриланс', 'Демо: додатковий проєкт', 5200 + $monthOffset * 350, 'income', $month->copy()->day(18)->hour(15)];
            $operations[] = ['Продукти', 'Демо: супермаркет', 5200 + $monthOffset * 280, 'expense', $month->copy()->day(7)->hour(18)];
            $operations[] = ['Продукти', 'Демо: продукти на тиждень', 3900 + $monthOffset * 120, 'expense', $month->copy()->day(21)->hour(17)];
            $operations[] = ['Транспорт', 'Демо: транспорт', 1800 + $monthOffset * 70, 'expense', $month->copy()->day(10)->hour(8)];
            $operations[] = ['Кафе', 'Демо: кафе та зустрічі', 2400 + $monthOffset * 90, 'expense', $month->copy()->day(13)->hour(20)];
            $operations[] = ['Комунальні', 'Демо: комунальні платежі', 3100 + $monthOffset * 160, 'expense', $month->copy()->day(16)->hour(12)];
            $operations[] = ['Підписки', 'Демо: онлайн-сервіси', 760, 'expense', $month->copy()->day(20)->hour(11)];

            if ($monthOffset % 2 === 0) {
                $operations[] = ['Здоровʼя', 'Демо: аптека', 1450 + $monthOffset * 80, 'expense', $month->copy()->day(24)->hour(14)];
            }
        }

        foreach ($operations as [$categoryName, $name, $amount, $type, $date]) {
            Operation::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $categories[$categoryName]->id,
                'name' => $name,
                'amount' => $amount,
                'type' => $type,
                'method' => 'card',
                'date' => $date,
            ]);
        }

        $account->update([
            'balance' => Operation::where('user_id', $user->id)
                ->sum(\DB::raw("CASE WHEN type = 'income' THEN amount ELSE -amount END")),
        ]);

        $categories->each(function (Category $category) use ($user) {
            $category->update([
                'amount' => Operation::where('user_id', $user->id)
                    ->where('category_id', $category->id)
                    ->sum('amount'),
            ]);
        });

        $user->update([
            'monthly_budget' => max((float) $user->monthly_budget, 26000),
            'notifications_enabled' => true,
        ]);
    }
}
