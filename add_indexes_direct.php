<?php

/**
 * سكريبت إضافة Indexes مباشرة إلى قاعدة البيانات PostgreSQL
 * 
 * التعليمات:
 * 1. انسخ هذا الملف إلى: C:\laragon\www\code_shell_server\
 * 2. نفذ: php add_indexes_direct.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔧 بدء إضافة Indexes لتحسين أداء استعلامات الكورسات...\n\n";

$indexes = [
    // course_subscriptions
    ['table' => 'course_subscriptions', 'columns' => ['user_id', 'course_id'], 'name' => 'idx_user_course'],
    ['table' => 'course_subscriptions', 'columns' => ['course_id'], 'name' => 'idx_cs_course_id'],
    ['table' => 'course_subscriptions', 'columns' => ['subscription_status'], 'name' => 'idx_subscription_status'],
    ['table' => 'course_subscriptions', 'columns' => ['group_id'], 'name' => 'idx_cs_group_id'],
    
    // course_reservations
    ['table' => 'course_reservations', 'columns' => ['course_id'], 'name' => 'idx_cr_course_id'],
    
    // levels
    ['table' => 'levels', 'columns' => ['course_id'], 'name' => 'idx_levels_course_id'],
    ['table' => 'levels', 'columns' => ['plan_id'], 'name' => 'idx_levels_plan_id'],
    
    // lessons
    ['table' => 'lessons', 'columns' => ['level_id'], 'name' => 'idx_lessons_level_id'],
    
    // courses
    ['table' => 'courses', 'columns' => ['is_active'], 'name' => 'idx_courses_is_active'],
    ['table' => 'courses', 'columns' => ['category_id'], 'name' => 'idx_courses_category_id'],
    
    // course_groups
    ['table' => 'course_groups', 'columns' => ['course_id', 'status'], 'name' => 'idx_cg_course_status'],
];

$successCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($indexes as $index) {
    $table = $index['table'];
    $columns = $index['columns'];
    $name = $index['name'];
    
    try {
        // التحقق من وجود الجدول
        if (!Schema::hasTable($table)) {
            echo "⏭️  تخطي: الجدول {$table} غير موجود\n";
            $skippedCount++;
            continue;
        }
        
        // التحقق من وجود الـ index
        $exists = DB::select("
            SELECT COUNT(*) as count 
            FROM pg_indexes 
            WHERE schemaname = 'public' 
            AND tablename = ? 
            AND indexname = ?
        ", [$table, $name]);
        
        if ($exists[0]->count > 0) {
            echo "✅ موجود مسبقاً: {$name} على {$table}\n";
            $skippedCount++;
            continue;
        }
        
        // إنشاء الـ index
        $columnsList = implode(', ', $columns);
        DB::statement("CREATE INDEX {$name} ON {$table} ({$columnsList})");
        
        echo "🎉 تم الإضافة: {$name} على {$table} ({$columnsList})\n";
        $successCount++;
        
    } catch (\Exception $e) {
        echo "❌ خطأ في {$table}.{$name}: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n";
echo "=" . str_repeat("=", 50) . "\n";
echo "📊 النتيجة النهائية:\n";
echo "   ✅ تم الإضافة: {$successCount}\n";
echo "   ⏭️  موجود مسبقاً: {$skippedCount}\n";
echo "   ❌ أخطاء: {$errorCount}\n";
echo "=" . str_repeat("=", 50) . "\n";

if ($successCount > 0) {
    echo "\n🚀 تم إضافة Indexes بنجاح! السرعة المتوقعة: 10-100 مرة أسرع\n";
    echo "💡 يُنصح بإعادة تشغيل السيرفر: php artisan serve\n";
}

echo "\nتم! ✨\n";
