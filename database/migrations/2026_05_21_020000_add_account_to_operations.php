<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operations') && !Schema::hasColumn('operations', 'account_id')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->unsignedInteger('account_id')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operations') && Schema::hasColumn('operations', 'account_id')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }
};
