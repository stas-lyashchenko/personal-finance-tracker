<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Operation;
use App\Support\BudgetLimitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

class ImportedOperationController extends Controller
{
    private array $categoryKeywords = [
        'Зарплата' => ['salary', 'зарплата', 'зароб', 'payroll'],
        'Дім та ремонт' => ['дім', 'ремонт', 'будівництво', 'інтернет', 'телефон'],
        'Зарахування' => ['зарахування', 'зарахування'],
        'Перекази' => ['переказ', 'перекази'],
        'Аптеки' => ['аптека', 'аптеки', 'ліки'],
        'Зняття готівки' => ['зняття'],
        'Одяг та взуття' => ['одяг', 'одіжка', 'взуття'],
        'Освіта' => ['освіта', 'школа', 'університет'],
        'Платежі' => ['платіж', 'платеж'],
        'Поповнення мобільного' => ['поповнення', 'мобільн'],
        'Ресторани, кафе, бари' => ['ресторан', 'кафе', 'бар'],
        'Поїздки' => ['таксі', 'поїздка', 'транспорт'],
        'Продукти' => ['атб', 'сільпо', 'silpo', 'fora', 'novus', 'metro', 'varus', 'auchan', 'маркет', 'супермаркет', 'продукти', 'magazinsmakota', 'ivushka', 'shop koshyk', 'кошик'],
        'Транспорт' => ['миколаївелектротранс', 'тролейбус', 'uber', 'bolt', 'uklon', 'taxi', 'таксі', 'wog', 'okko', 'shell', 'upg'],
        'Підписки' => ['netflix', 'spotify', 'youtube', 'apple', 'google', 'subscription'],
        'Краса та догляд' => ['eva', 'prostor', 'watsons', 'makeup'],
        'Кафе' => ['кафе', 'ресторан', 'coffee', 'кав'],
        'Комунальні' => ['комунал', 'інтернет', 'телефон', 'електро', 'газ', 'вода']
    ];

    public function import(Request $request, BudgetLimitService $budgetLimitService)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'bank' => 'required|string',
        ]);

        $rows = $this->readRows($request->file('file'));

        if (count($rows) < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Файл порожній або має невідомий формат.',
            ], 422);
        }

        [$headers, $dataRows] = $this->splitHeaderAndRows($rows, $request->bank);
        $statementCard = $this->statementCard($rows);

        $count = 0;
        $skipped = 0;
        $duplicates = 0;
        $latestAccountBalances = [];

        foreach ($dataRows as $row) {
            $transaction = $this->extractTransaction($row, $headers, $request->bank);

            if (!$transaction['date'] || !$transaction['description'] || $transaction['amount'] === null) {
                $skipped++;
                continue;
            }

            $type = $transaction['amount'] < 0 ? 'expense' : 'income';
            $account = $this->accountForTransaction($request->bank, $transaction['card'] ?? $statementCard);

            if ($transaction['balance_after'] !== null) {
                $knownBalance = $latestAccountBalances[$account->id] ?? null;

                if (!$knownBalance || $transaction['date'] > $knownBalance['date']) {
                    $latestAccountBalances[$account->id] = [
                        'date' => $transaction['date'],
                        'balance' => $transaction['balance_after'],
                    ];
                }
            }

            $amount = abs($transaction['amount']);

            if ($this->operationAlreadyImported($transaction['date'], $transaction['description'], $amount, $type)) {
                $duplicates++;
                continue;
            }

            $category = $this->detectCategory($transaction['description'], $type, $transaction['category'] ?? null);

            DB::transaction(function () use ($account, $category, $transaction, $type, $amount) {
                $operation = Operation::create([
                    'user_id' => Auth::id(),
                    'account_id' => $account->id,
                    'category_id' => $category->id,
                    'name' => $transaction['description'],
                    'amount' => $amount,
                    'type' => $type,
                    'method' => 'card',
                    'date' => $transaction['date']
                ]);

                $this->applyBalanceEffect($operation);
            });

            $count++;
        }

        foreach ($latestAccountBalances as $accountId => $balanceData) {
            Account::where('user_id', Auth::id())
                ->whereKey($accountId)
                ->update(['balance' => $balanceData['balance']]);
        }

        if ($count === 0 && $duplicates === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Не вдалося знайти операції у файлі. Спробуйте експортувати виписку у CSV або XLSX.',
                'skipped' => $skipped,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'count' => $count,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'budget_warning' => $budgetLimitService->warningMessage(Auth::user()),
        ]);
    }

    private function statementCard(array $rows): ?string
    {
        foreach (array_slice($rows, 0, 50) as $row) {
            foreach ($row as $cell) {
                $cell = $this->cleanDescription($cell);

                if ($cell && preg_match('/\d{4}\s+\*{4}\s+\*{4}\s+\d{4}/u', $cell, $match)) {
                    return $match[0];
                }
            }
        }

        return null;
    }

    private function operationAlreadyImported(string $date, string $description, float $amount, string $type): bool
    {
        return Operation::where('user_id', Auth::id())
            ->where('date', $date)
            ->where('name', $description)
            ->where('amount', round($amount, 2))
            ->where('type', $type)
            ->where('method', 'card')
            ->exists();
    }

    private function readRows(UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx' || $this->isZipSpreadsheet($file->getRealPath())) {
            return $this->readXlsxRows($file->getRealPath());
        }

        return $this->readCsvRows($file->getRealPath());
    }

    private function isZipSpreadsheet(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if (!$handle) {
            return false;
        }

        $signature = fread($handle, 4);
        fclose($handle);

        return $signature === "PK\x03\x04";
    }

    private function readCsvRows(string $path): array
    {
        $content = file_get_contents($path);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'CP1251', 'ISO-8859-1'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $delimiter = $this->detectDelimiter($lines);
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = array_map('trim', str_getcsv($line, $delimiter));
        }

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readXlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $sheetRow) {
            $row = [];
            $maxIndex = -1;

            foreach ($sheetRow->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $index = $this->columnIndex((string) $cell['r']);
                $maxIndex = max($maxIndex, $index);
                $type = (string) $cell['t'];
                $valueNode = ($cell->xpath('./*[local-name()="v"]') ?: [null])[0];
                $value = $valueNode ? (string) $valueNode : '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $inlineNode = ($cell->xpath('./*[local-name()="is"]') ?: [null])[0];
                    $value = $inlineNode ? $this->xlsxText($inlineNode) : '';
                }

                $row[$index] = trim($value);
            }

            if ($row) {
                $rows[] = array_map(
                    fn($index) => $row[$index] ?? '',
                    range(0, $maxIndex)
                );
            }
        }

        return $rows;
    }

    private function readXlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (!$xml) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        $strings = [];

        foreach ($shared->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $strings[] = $this->xlsxText($item);
        }

        return $strings;
    }

    private function xlsxText(\SimpleXMLElement $node): string
    {
        $text = '';

        foreach ($node->xpath('.//*[local-name()="t"]') ?: [] as $part) {
            $text .= (string) $part;
        }

        return $text !== '' ? $text : (string) $node->t;
    }

    private function columnIndex(string $cellReference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellReference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function detectDelimiter(array $lines): string
    {
        $sample = collect($lines)->first(fn($line) => trim($line) !== '') ?? '';
        $delimiters = [';' => 0, ',' => 0, "\t" => 0];

        foreach (array_keys($delimiters) as $delimiter) {
            $delimiters[$delimiter] = count(str_getcsv($sample, $delimiter));
        }

        arsort($delimiters);

        return array_key_first($delimiters);
    }

    private function splitHeaderAndRows(array $rows, string $bank): array
    {
        foreach (array_slice($rows, 0, 50, true) as $index => $row) {
            $headers = $this->mapHeaders($row, $bank);

            if ($this->hasRequiredHeaders($headers)) {
                return [$headers, array_slice($rows, $index + 1)];
            }
        }

        return [null, $rows];
    }

    private function hasRequiredHeaders(array $headers): bool
    {
        return isset($headers['date'], $headers['description'])
            && (isset($headers['amount']) || isset($headers['expense']) || isset($headers['income']));
    }

    private function mapHeaders(array $headers, string $bank): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $header = $this->normalize($header);

            if ($this->matchesHeader($header, ['дата', 'date'])) {
                $map['date'] ??= $index;
            }

            if ($this->matchesHeader($category = $header, ['категор', 'category', 'категорія'])) {
                $map['category'] ??= $index;
            }

            if (
                $this->matchesHeader($header, ['РєР°СЂС‚', 'рљр°сђс‚', 'card'])
                || ($this->matchesHeader($header, ['карт']) && !$this->matchesHeader($header, ['сума', 'валют']))
            ) {
                $map['card'] ??= $index;
            }

            if ($this->matchesHeader($header, ['опис', 'description', 'details', 'деталі', 'призначення', 'контрагент', 'merchant'])) {
                $map['description'] ??= $index;
            }

            if ($this->matchesHeader($header, ['сума', 'amount', 'сумма'])) {
                $map['amount'] ??= $index;
            }

            if ($this->matchesHeader($header, ['залишок', 'balance'])) {
                $map['balance_after'] ??= $index;
            }

            if ($this->matchesHeader($header, ['витрата', 'списання', 'debit'])) {
                $map['expense'] ??= $index;
            }

            if ($this->matchesHeader($header, ['дохід', 'надходження', 'зарахування', 'credit'])) {
                $map['income'] ??= $index;
            }
        }

        return $map;
    }

    private function matchesHeader(string $header, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($header, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function extractTransaction(array $row, ?array $headers, string $bank): array
    {
        if ($headers) {
            $amount = isset($headers['amount']) ? $this->parseAmount($row[$headers['amount']] ?? null) : null;

            if ($amount === null && isset($headers['expense'])) {
                $expense = $this->parseAmount($row[$headers['expense']] ?? null);
                $amount = $expense !== null ? -abs($expense) : null;
            }

            if (($amount === null || $amount == 0.0) && isset($headers['income'])) {
                $income = $this->parseAmount($row[$headers['income']] ?? null);
                $amount = $income !== null ? abs($income) : $amount;
            }

            return [
                'date' => $this->parseDate($row[$headers['date']] ?? null),
                'description' => $this->cleanDescription($row[$headers['description']] ?? null),
                'amount' => $amount,
                'category' => $this->cleanDescription(isset($headers['category']) ? ($row[$headers['category']] ?? null) : null),
                'card' => $this->cleanDescription(isset($headers['card']) ? ($row[$headers['card']] ?? null) : null),
                'balance_after' => isset($headers['balance_after']) ? $this->parseAmount($row[$headers['balance_after']] ?? null) : null,
            ];
        }

        return $this->inferTransaction($row, $bank);
    }

    private function inferTransaction(array $row, string $bank): array
    {
        $date = null;
        $amount = null;

        foreach ($row as $cell) {
            $date ??= $this->parseDate($cell);
        }

        foreach (array_reverse($row) as $cell) {
            $parsedAmount = $this->parseAmount($cell);

            if ($parsedAmount !== null && !$this->parseDate($cell)) {
                $amount = $parsedAmount;
                break;
            }
        }

        $textCells = collect($row)
            ->filter(fn($cell) => $this->parseDate($cell) === null && $this->parseAmount($cell) === null)
            ->map(fn($cell) => $this->cleanDescription($cell))
            ->filter()
            ->sortByDesc(fn($cell) => mb_strlen($cell))
            ->values();

        return [
            'date' => $date,
            'description' => $textCells->first(),
            'amount' => $amount,
            'category' => null,
            'card' => null,
            'balance_after' => null,
        ];
    }

    private function accountForTransaction(string $bank, ?string $card): Account
    {
        $card = $this->cleanDescription($card);
        $name = $this->bankLabel($bank) . ($card ? ' / ' . $card : '');

        return Account::firstOrCreate(
            [
                'user_id' => Auth::id(),
                'name' => $name,
            ],
            [
                'type' => 'account',
                'balance' => 0,
                'icon' => 'card/card.png',
                'color' => 'gray',
            ]
        );
    }

    private function bankLabel(string $bank): string
    {
        return match ($bank) {
            'privat' => 'ПриватБанк',
            'mono' => 'Monobank',
            'pumb' => 'ПУМБ',
            'oschad' => 'Ощадбанк',
            default => Str::title($bank),
        };
    }

    private function detectCategory(string $description, string $type, ?string $importedCategory = null): Category
    {
        $userId = Auth::id();

        $normalizedDescription = $this->normalize($description);
        $normalizedImportedCategory = $this->normalize($importedCategory);

        // Спочатку перевіряємо Категорію з файлу
        foreach ($this->categoryKeywords as $name => $keywords) {
            foreach ($keywords as $keyword) {

                $normalizedKeyword = $this->normalize($keyword);

                if (
                    str_contains($normalizedImportedCategory, $normalizedKeyword) ||
                    str_contains($normalizedDescription, $normalizedKeyword)
                ) {
                    return Category::firstOrCreate(
                        [
                            'user_id' => $userId,
                            'name' => $name,
                            'type' => $type
                        ],
                        [
                            'amount' => 0,
                            'icon' => $type === 'income'
                                ? 'coins/coins-green.png'
                                : 'images/default.png',
                            'color' => $type === 'income'
                                ? 'green'
                                : 'gray',
                        ]
                    );
                }
            }
        }

        // Якщо назва категорії з файлу вже існує
        if ($importedCategory) {
            $existingCategory = Category::where('user_id', $userId)
                ->where('type', $type)
                ->get()
                ->first(
                    fn(Category $category) =>
                    $this->normalize($category->name) === $normalizedImportedCategory
                );

            if ($existingCategory) {
                return $existingCategory;
            }
        }

        return Category::firstOrCreate(
            [
                'user_id' => $userId,
                'name' => $importedCategory ?: Str::title(Str::limit($description, 30, '')),
                'type' => $type
            ],
            [
                'amount' => 0,
                'icon' => $type === 'income'
                    ? 'coins/coins-green.png'
                    : 'images/default.png',
                'color' => $type === 'income'
                    ? 'green'
                    : 'gray',
            ]
        );
    }

    private function applyBalanceEffect(Operation $operation): void
    {
        if (!$operation->account_id) {
            return;
        }

        $account = Account::where('user_id', Auth::id())->find($operation->account_id);

        if (!$account) {
            return;
        }

        $direction = $operation->type === 'income' ? 1 : -1;
        $account->increment('balance', $direction * (float) $operation->amount);
        $this->applyCategoryEffect($operation);
    }

    private function applyCategoryEffect(Operation $operation): void
    {
        if (!$operation->category_id) {
            return;
        }

        $category = Category::where('user_id', Auth::id())->find($operation->category_id);

        if (!$category) {
            return;
        }

        $category->increment('amount', (float) $operation->amount);
    }

    private function parseAmount(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace(["\u{00A0}", ' '], '', $value));

        if ($value === '' || !preg_match('/\d/', $value)) {
            return null;
        }

        $isNegative = str_contains($value, '-') || (str_starts_with($value, '(') && str_ends_with($value, ')'));
        $value = preg_replace('/[^\d,.\-]/u', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $decimalSeparator = strrpos($value, ',') > strrpos($value, '.') ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        $amount = (float) $value;

        return $isNegative ? -abs($amount) : $amount;
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_numeric($value) && (float) $value > 30000 && (float) $value < 60000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d H:i:s');
        }

        if (!preg_match('/\d{1,4}[.\/-]\d{1,2}[.\/-]\d{1,4}/', $value)) {
            return null;
        }

        $value = trim($value);
        $formats = [
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd.m.Y',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description !== '' ? Str::limit($description, 255, '') : null;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
