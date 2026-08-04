<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة عمود عداد التنزيلات الحقيقي لكل إصدار منشور
     */
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('downloads_count')->default(0)->after('changelog');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn('downloads_count');
        });
    }
};
