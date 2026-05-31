<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type');
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('icon');
                $table->string('color');
            });
        } elseif (!Schema::hasColumn('accounts', 'user_id')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('icon');
                $table->string('color');
            });
        } elseif (!Schema::hasColumn('categories', 'user_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasTable('operations')) {
            Schema::create('operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('type');
                $table->string('method');
                $table->string('color')->nullable();
                $table->dateTime('date')->nullable();
            });
        } elseif (!Schema::hasColumn('operations', 'user_id')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'currency')) {
                $table->string('currency', 8)->default('UAH')->after('password');
            }

            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 16)->default('light')->after('currency');
            }

            if (!Schema::hasColumn('users', 'monthly_budget')) {
                $table->decimal('monthly_budget', 12, 2)->default(0)->after('theme');
            }

            if (!Schema::hasColumn('users', 'notifications_enabled')) {
                $table->boolean('notifications_enabled')->default(true)->after('monthly_budget');
            }
        });
    }

    public function down(): void
    {
        foreach (['operations', 'categories', 'accounts'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('user_id');
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['currency', 'theme', 'monthly_budget', 'notifications_enabled'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
