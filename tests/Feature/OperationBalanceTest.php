<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite PDO driver is not available in this PHP installation.');
        }

        parent::setUp();
    }

    public function test_operations_apply_update_and_reverse_account_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Main card',
            'type' => 'account',
            'balance' => 1000,
            'icon' => 'card/card.png',
            'color' => 'gray',
        ]);
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Food',
            'type' => 'expense',
            'amount' => 0,
            'icon' => 'bag/bag-black.png',
            'color' => 'gray',
        ]);

        $this->actingAs($user)->post(route('operations.store'), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'name' => 'Groceries',
            'amount' => 250,
            'type' => 'expense',
            'method' => 'card',
        ])->assertRedirect();

        $this->assertSame('750.00', $account->fresh()->balance);
        $this->assertSame('250.00', $category->fresh()->amount);

        $operation = Operation::firstOrFail();
        $this->actingAs($user)->put(route('operations.update', $operation), [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'name' => 'Groceries',
            'amount' => 400,
            'type' => 'expense',
            'method' => 'card',
        ])->assertJson(['success' => true]);

        $this->assertSame('600.00', $account->fresh()->balance);
        $this->assertSame('400.00', $category->fresh()->amount);

        $this->actingAs($user)->delete(route('operations.destroy', $operation->fresh()))
            ->assertJson(['success' => true]);

        $this->assertSame('1000.00', $account->fresh()->balance);
        $this->assertSame('0.00', $category->fresh()->amount);
    }
}
