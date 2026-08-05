<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة عمود prices (JSON) لتخزين أسعار الكورس بكل العملات
     * بشكل ثابت ومستقل (مثال: {"EGP": 300, "SAR": 40, ...}).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('courses', 'prices')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->json('prices')->nullable()->after('price');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('prices');
        });
    }
};
