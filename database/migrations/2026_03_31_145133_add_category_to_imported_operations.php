<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('imported_operations') && !Schema::hasColumn('imported_operations', 'category')) {
            Schema::table('imported_operations', function (Blueprint $table) {
                $table->string('category')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('imported_operations') && Schema::hasColumn('imported_operations', 'category')) {
            Schema::table('imported_operations', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
