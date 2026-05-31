<?php

namespace Tests\Unit;

use App\Http\Controllers\ImportedOperationController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ImportedOperationControllerTest extends TestCase
{
    public function test_privat_xlsx_rows_with_report_title_are_mapped_correctly(): void
    {
        $controller = new ImportedOperationController();
        $rows = [
            ['Історія операцій за період 01.01.2025 - 01.01.2025'],
            ['Дата', 'Категорія', 'Картка', 'Опис операції', 'Сума в валюті картки', 'Валюта картки', 'Сума в валюті транзакції', 'Валюта транзакції', 'Залишок на кінець періоду', 'Валюта залишку'],
            ['01.01.2025 15:57:05', 'Дім та ремонт', '5168 **** **** 2868', 'Аврора', '-161.0', 'UAH', '161.0', 'UAH', '1088.81', 'UAH'],
        ];

        [$headers, $dataRows] = $this->invoke($controller, 'splitHeaderAndRows', [$rows, 'privat']);
        $transaction = $this->invoke($controller, 'extractTransaction', [$dataRows[0], $headers, 'privat']);

        $this->assertSame([
            'date' => 0,
            'category' => 1,
            'card' => 2,
            'description' => 3,
            'amount' => 4,
            'balance_after' => 8,
        ], $headers);
        $this->assertCount(1, $dataRows);
        $this->assertSame('2025-01-01 15:57:05', $transaction['date']);
        $this->assertSame('Аврора', $transaction['description']);
        $this->assertSame(-161.0, $transaction['amount']);
        $this->assertSame($dataRows[0][1], $transaction['category']);
        $this->assertSame('5168 **** **** 2868', $transaction['card']);
        $this->assertSame(1088.81, $transaction['balance_after']);
    }

    public function test_monobank_statement_card_is_read_from_report_header(): void
    {
        $controller = new ImportedOperationController();
        $rows = [
            ['Клієнт: Test User'],
            ['Інформація по картці: 4441 **** **** 4829'],
            ['Дата i час операції', 'Деталі операції', 'Сума в валюті картки (UAH)', 'Залишок після операції'],
        ];

        $this->assertSame('4441 **** **** 4829', $this->invoke($controller, 'statementCard', [$rows]));
    }

    private function invoke(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invokeArgs($object, $args);
    }
}
