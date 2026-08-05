<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * علامة حساب الأدمن داخل جدول المستخدمين:
     * حساب الأدمن (admin@codeshell.com) يُستخدم أيضاً في تطبيق الطالب
     * وبفضل هذه العلامة تُفتح له جميع الكورسات تلقائياً دون اشتراك.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
