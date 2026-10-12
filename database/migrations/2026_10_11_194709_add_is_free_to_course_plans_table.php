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
        Schema::table('course_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('course_plans', 'is_free')) {
                $table->boolean('is_free')->default(false)->after('price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_plans', function (Blueprint $table) {
            if (Schema::hasColumn('course_plans', 'is_free')) {
                $table->dropColumn('is_free');
            }
        });
    }
};
