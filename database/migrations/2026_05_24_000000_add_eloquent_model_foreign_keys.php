<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->prepareNullableRelation('operations', 'account_id', 'accounts');
            $this->prepareNullableRelation('operations', 'category_id', 'categories');
            $this->prepareNullableRelation('operations', 'user_id', 'users');
            $this->prepareNullableRelation('accounts', 'user_id', 'users');
            $this->prepareNullableRelation('categories', 'user_id', 'users');

            $this->matchForeignColumnType('operations', 'account_id', 'accounts');
            $this->matchForeignColumnType('operations', 'category_id', 'categories');
        }

        $this->addForeignKey('accounts', 'user_id', 'users', true);
        $this->addForeignKey('categories', 'user_id', 'users', true);
        $this->addForeignKey('operations', 'user_id', 'users', true);
        $this->addForeignKey('operations', 'account_id', 'accounts');
        $this->addForeignKey('operations', 'category_id', 'categories');
    }

    public function down(): void
    {
        $this->dropForeignKey('operations', 'category_id');
        $this->dropForeignKey('operations', 'account_id');
        $this->dropForeignKey('operations', 'user_id');
        $this->dropForeignKey('categories', 'user_id');
        $this->dropForeignKey('accounts', 'user_id');
    }

    private function addForeignKey(string $tableName, string $columnName, string $relatedTable, bool $cascade = false): void
    {
        if (
            !Schema::hasTable($tableName)
            || !Schema::hasTable($relatedTable)
            || !Schema::hasColumn($tableName, $columnName)
            || $this->hasForeignKey($tableName, $columnName)
        ) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $relatedTable, $cascade) {
            $foreign = $table->foreign($columnName)->references('id')->on($relatedTable);
            $cascade ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
        });
    }

    private function dropForeignKey(string $tableName, string $columnName): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, $columnName) || !$this->hasForeignKey($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $columnName) {
            $table->dropForeign($tableName . '_' . $columnName . '_foreign');
        });
    }

    private function prepareNullableRelation(string $tableName, string $columnName, string $relatedTable): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasTable($relatedTable) || !Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        DB::statement(
            "UPDATE {$tableName}
             LEFT JOIN {$relatedTable} ON {$tableName}.{$columnName} = {$relatedTable}.id
             SET {$tableName}.{$columnName} = NULL
             WHERE {$tableName}.{$columnName} IS NOT NULL
             AND {$relatedTable}.id IS NULL"
        );
    }

    private function matchForeignColumnType(string $tableName, string $columnName, string $relatedTable): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasTable($relatedTable) || !Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        $relatedColumn = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $relatedTable)
            ->where('COLUMN_NAME', 'id')
            ->value('COLUMN_TYPE');

        if (!$relatedColumn) {
            return;
        }

        DB::statement("ALTER TABLE {$tableName} MODIFY {$columnName} {$relatedColumn} NULL");
    }

    private function hasForeignKey(string $tableName, string $columnName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
