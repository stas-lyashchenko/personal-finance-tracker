<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurrencyPreferenceConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite PDO driver is not available in this PHP installation.');
        }

        parent::setUp();
    }

    public function test_currency_change_converts_user_money_with_latest_rate(): void
    {
        Cache::store('file')->forget('nbu_currency_rates_to_uah_v2');
        Cache::store('file')->forget('nbu_currency_rates_to_uah_last_successful');
        Http::fake([
            'bank.gov.ua/*' => Http::response([
                ['cc' => 'USD', 'rate' => 40],
                ['cc' => 'EUR', 'rate' => 43],
                ['cc' => 'PLN', 'rate' => 10],
            ]),
        ]);

        $user = User::factory()->create([
            'currency' => 'UAH',
            'monthly_budget' => 1000,
            'notifications_enabled' => true,
        ]);
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Main card',
            'type' => 'account',
            'balance' => 4000,
            'icon' => 'card/card.png',
            'color' => 'gray',
        ]);
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
            'amount' => 500,
            'icon' => 'bag/bag-black.png',
            'color' => 'gray',
        ]);
        $operation = Operation::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'name' => 'Groceries',
            'amount' => 100,
            'type' => 'expense',
            'method' => 'card',
            'date' => now(),
        ]);

        $this->actingAs($user)->put(route('more.preferences'), [
            'theme' => 'light',
            'currency' => 'USD',
            'monthly_budget' => 1000,
            'notifications_enabled' => 1,
        ])->assertRedirect();

        $this->assertSame('USD', $user->fresh()->currency);
        $this->assertSame('25.00', $user->fresh()->monthly_budget);
        $this->assertSame('100.00', $account->fresh()->balance);
        $this->assertSame('12.50', $category->fresh()->amount);
        $this->assertSame('2.50', $operation->fresh()->amount);
    }
}
