#!/usr/bin/env php
<?php

/**
 * إضافة Indexes إلى قاعدة البيانات على سيرفر الإنتاج (Railway)
 * 
 * الاستخدام:
 * php add_indexes_production.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔧 بدء إضافة Database Indexes للإنتاج...\n";
echo "📊 قاعدة البيانات: " . config('database.default') . "\n";
echo "🔗 اتصال: " . config('database.connections.' . config('database.default') . '.host') . "\n\n";

$indexes = [
    ['table' => 'course_subscriptions', 'columns' => ['user_id', 'course_id'], 'name' => 'idx_user_course'],
    ['table' => 'course_subscriptions', 'columns' => ['course_id'], 'name' => 'idx_cs_course_id'],
    ['table' => 'course_subscriptions', 'columns' => ['subscription_status'], 'name' => 'idx_subscription_status'],
    ['table' => 'course_subscriptions', 'columns' => ['group_id'], 'name' => 'idx_cs_group_id'],
    ['table' => 'course_reservations', 'columns' => ['course_id'], 'name' => 'idx_cr_course_id'],
    ['table' => 'levels', 'columns' => ['course_id'], 'name' => 'idx_levels_course_id'],
    ['table' => 'levels', 'columns' => ['plan_id'], 'name' => 'idx_levels_plan_id'],
    ['table' => 'lessons', 'columns' => ['level_id'], 'name' => 'idx_lessons_level_id'],
    ['table' => 'courses', 'columns' => ['is_active'], 'name' => 'idx_courses_is_active'],
    ['table' => 'courses', 'columns' => ['category_id'], 'name' => 'idx_courses_category_id'],
    ['table' => 'course_groups', 'columns' => ['course_id', 'status'], 'name' => 'idx_cg_course_status'],
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($indexes as $index) {
    $table = $index['table'];
    $columns = $index['columns'];
    $name = $index['name'];
    
    try {
        if (!Schema::hasTable($table)) {
            echo "⏭️  تخطي: {$table} غير موجود\n";
            $skipped++;
            continue;
        }
        
        // فحص وجود Index (PostgreSQL)
        $check = DB::select("
            SELECT COUNT(*) as count 
            FROM pg_indexes 
            WHERE schemaname = 'public' 
            AND tablename = ? 
            AND indexname = ?
        ", [$table, $name]);
        
        if ($check[0]->count > 0) {
            echo "✅ موجود: {$name}\n";
            $skipped++;
            continue;
        }
        
        // إنشاء Index
        $cols = implode(', ', $columns);
        DB::statement("CREATE INDEX {$name} ON {$table} ({$cols})");
        echo "🎉 أُضيف: {$name} على {$table}\n";
        $success++;
        
    } catch (\Exception $e) {
        echo "❌ خطأ {$name}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 النتيجة:\n";
echo "   ✅ أُضيف: {$success}\n";
echo "   ⏭️  موجود: {$skipped}\n";
echo "   ❌ أخطاء: {$errors}\n";
echo str_repeat("=", 50) . "\n";

if ($success > 0) {
    echo "\n🚀 تم! السرعة المتوقعة: 10-100x أسرع\n";
    echo "💡 يُنصح بمسح الكاش: php artisan cache:clear\n";
}

exit(0);
