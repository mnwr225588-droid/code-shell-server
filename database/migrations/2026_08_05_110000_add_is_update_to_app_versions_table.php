<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * يميّز نوع الإصدار المرفوع من لوحة الأدمن:
     * - is_update = true  → يظهر في الموقع (زر التنزيل) ويُقترح كتحديث في تطبيق الطالب.
     * - is_update = false → يظهر في الموقع فقط ولا يُقترح كتحديث لتطبيق الطالب.
     * الإصدارات القديمة تبقى تحديثات (القيمة الافتراضية true).
     */
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->boolean('is_update')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn('is_update');
        });
    }
};
