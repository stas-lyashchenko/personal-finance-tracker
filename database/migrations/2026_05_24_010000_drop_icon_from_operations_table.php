<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operations') && Schema::hasColumn('operations', 'icon')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operations') && !Schema::hasColumn('operations', 'icon')) {
            Schema::table('operations', function (Blueprint $table) {
                $table->string('icon')->nullable()->after('method');
            });
        }
    }
};
