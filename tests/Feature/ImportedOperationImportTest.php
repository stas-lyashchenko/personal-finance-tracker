<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportedOperationImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('SQLite PDO driver is not available in this PHP installation.');
        }

        parent::setUp();
    }

    public function test_import_uses_excel_category_and_card_account(): void
    {
        $user = User::factory()->create();
        $defaultAccount = Account::create([
            'user_id' => $user->id,
            'name' => 'Default account',
            'type' => 'account',
            'balance' => 0,
            'icon' => 'card/card.png',
            'color' => 'gray',
        ]);
        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Utilities',
            'type' => 'expense',
            'amount' => 0,
            'icon' => 'house/house-gray.png',
            'color' => 'gray',
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'statement.csv',
            "date,category,card,description,amount\n"
            . "01.01.2025 15:57:05,Utilities,5168 **** **** 2868,coffee place,-161.00\n"
        );

        $this->actingAs($user)->post('/import', [
            'bank' => 'privat',
            'account_id' => $defaultAccount->id,
            'file' => $file,
        ])->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        $operation = Operation::firstOrFail();
        $cardAccount = Account::where('name', 'ПриватБанк / 5168 **** **** 2868')->firstOrFail();

        $this->assertSame($category->id, $operation->category_id);
        $this->assertSame($cardAccount->id, $operation->account_id);
        $this->assertSame('161.00', $category->fresh()->amount);
        $this->assertSame('-161.00', $cardAccount->fresh()->balance);
        $this->assertSame('0.00', $defaultAccount->fresh()->balance);
    }

    public function test_monobank_xls_template_imports_without_selected_account(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'report.xls',
            $this->xlsxContent([
                ['Клієнт: Test User'],
                ['Дата народження: 01.01.2000'],
                [''],
                ['Інформація по картці: 4441 **** **** 4829'],
                ['Рахунок: UA363220010000000000000000000'],
                ['Період: 02.10.2020 - 23.05.2026'],
                [''],
                ['Баланс на початок періоду: 0.00 UAH'],
                ['Баланс на кінець періоду: 5.92 UAH'],
                [''],
                [''],
                [''],
                [''],
                [''],
                [''],
                [''],
                [''],
                [''],
                [''],
                [''],
                ['Дата i час операції', 'Деталі операції', 'MCC', 'Сума в валюті картки (UAH)', 'Сума в валюті операції', 'Валюта', 'Курс', 'Сума комісій (UAH)', 'Сума кешбеку (UAH)', 'Залишок після операції'],
                ['15.11.2025 16:44:22', 'Миколаївелектротранс Тролейбус', '4111', '-16', '-16', 'UAH', '—', '—', '—', '5.92'],
            ])
        );

        $this->actingAs($user)->post('/import', [
            'bank' => 'mono',
            'file' => $file,
        ])->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        $operation = Operation::firstOrFail();
        $account = Account::where('name', 'Monobank / 4441 **** **** 4829')->firstOrFail();
        $category = Category::where('name', 'Транспорт')->firstOrFail();

        $this->assertSame($account->id, $operation->account_id);
        $this->assertSame($category->id, $operation->category_id);
        $this->assertSame('5.92', $account->fresh()->balance);
        $this->assertSame('16.00', $category->fresh()->amount);
    }

    private function xlsxContent(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mono-xlsx-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();

        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }

    private function sheetXml(array $rows): string
    {
        $sheetRows = '';

        foreach ($rows as $rowIndex => $row) {
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex + 1) . ($rowIndex + 1);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cells .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }

            $sheetRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $sheetRows
            . '</sheetData></worksheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
