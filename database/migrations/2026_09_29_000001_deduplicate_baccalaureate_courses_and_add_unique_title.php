<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إصلاح جذري لمشكلة تكرار كورس البكالوريا (54 نسخة مكررة كانت موجودة):
     * 1. دمج كل كورسات البكالوريا المكررة في كورس واحد مع إعادة ربط الأبناء.
     * 2. إضافة قيد فريد (Unique) على عمود العنوان لمنع تكرار أي كورس مستقبلاً
     *    حتى لو فشلت مطابقة النصوص العربية في طبقة الاستعلامات.
     */
    public function up(): void
    {
        // 1. دمج المكررات أولاً (شرط أساسي لنجاح إنشاء القيد الفريد)
        try {
            $stats = \App\Models\Course::deduplicateBaccalaureate();
            if ($stats['deleted'] > 0) {
                Log::info('Migration deduplicated baccalaureate courses: ', $stats);
            }
        } catch (\Throwable $e) {
            Log::error('Baccalaureate deduplication failed in migration: ' . $e->getMessage());
        }

        // 2. قيد فريد على العنوان (مع تحقق مسبق من عدم وجوده أو وجود تكرارات متبقية)
        try {
            $hasDuplicates = \DB::table('courses')
                ->select('title')
                ->groupBy('title')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasDuplicates) {
                Log::warning('Unique title index skipped: duplicate course titles still exist.');
                return;
            }

            if (!Schema::hasIndex('courses', 'courses_title_unique')) {
                Schema::table('courses', function (Blueprint $table) {
                    $table->unique('title');
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Could not add unique title index: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            if (Schema::hasIndex('courses', 'courses_title_unique')) {
                Schema::table('courses', function (Blueprint $table) {
                    $table->dropUnique(['title']);
                });
            }
        } catch (\Throwable $e) {
            // تجاهل
        }
    }
};
