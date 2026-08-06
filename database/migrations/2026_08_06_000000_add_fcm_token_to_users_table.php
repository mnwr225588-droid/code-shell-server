<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة عمود fcm_token لجدول المستخدمين.
     * يُستخدم لحفظ توكن جهاز الطالب (Firebase) لاستقبال الإشعارات.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('remember_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
