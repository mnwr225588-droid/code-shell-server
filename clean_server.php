<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;

echo "=== بدء عملية تنظيف السيرفر (مع الحفاظ على هياكل الجداول) ===\n";

try {
    // حذف بيانات الجداول المرتبطة بالترتيب لتجنب قيود المفتاح الأجنبي (Foreign Keys)
    
    $tables = [
        'lecture_attendances',
        'lecture_postponement_requests',
        'online_lectures',
        'course_groups',
        'course_subscriptions',
        'course_reservations',
        'progress',
        'lesson_completions',
        'notifications',
        'notification_sends',
        'transactions',
        'personal_access_tokens',
    ];

    foreach ($tables as $table) {
        if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
            DB::table($table)->delete();
            echo "- تم تفريغ جدول: {$table}\n";
        }
    }

    // حذف المستخدمين الطلاب فقط (إبقاء حسابات المشرفين Admin لكي لا تفقد الوصول للوحة التحكم)
    $deletedUsersCount = User::where('is_admin', false)->orWhereNull('is_admin')->delete();
    echo "- تم حذف {$deletedUsersCount} من المستخدمين (الطلاب)، مع الإبقاء على حسابات المشرفين (Admins).\n";

    echo "\n=== تم التنظيف بنجاح! جميع المجموعات، المحاضرات، والطلاب تم حذفهم وبقيت الجداول سليمة. ===\n";

} catch (\Exception $e) {
    echo "حدث خطأ أثناء التنظيف: " . $e->getMessage() . "\n";
}
