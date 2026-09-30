<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة حقول ربط المستويات بالمجموعات
     * - level_id: المستوى المرتبط بالمجموعة (اختياري)
     * - include_all_levels: تضمين جميع المستويات في المجموعة
     */
    public function up(): void
    {
        Schema::table('course_groups', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->after('teacher_id')->constrained('levels')->nullOnDelete();
            $table->boolean('include_all_levels')->default(false)->after('level_id')->comment('تضمين جميع المستويات في المجموعة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropColumn(['level_id', 'include_all_levels']);
        });
    }
};
