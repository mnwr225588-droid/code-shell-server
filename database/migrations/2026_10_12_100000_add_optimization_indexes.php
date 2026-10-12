<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة Indexes لتحسين أداء استعلامات قاعدة البيانات
     * هذه الـ Indexes تساعد في تسريع جلب الكورسات والإحصائيات
     */
    public function up(): void
    {
        // Indexes لجدول courses
        Schema::table('courses', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('category_id');
            $table->index(['is_active', 'id'], 'courses_active_id_index');
        });

        // Indexes لجدول course_subscriptions
        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'course_id']);
            $table->index(['course_id', 'subscription_status']);
            $table->index('subscription_status');
            $table->index('user_id');
            $table->index('course_id');
        });

        // Indexes لجدول course_reservations
        Schema::table('course_reservations', function (Blueprint $table) {
            $table->index('course_id');
            $table->index('user_id');
        });

        // Indexes لجدول levels
        Schema::table('levels', function (Blueprint $table) {
            $table->index('course_id');
            $table->index(['course_id', 'id']);
        });

        // Indexes لجدول lessons
        Schema::table('lessons', function (Blueprint $table) {
            $table->index('level_id');
            $table->index(['level_id', 'course_id']);
        });

        // Indexes لجدول course_groups
        Schema::table('course_groups', function (Blueprint $table) {
            $table->index('course_id');
            $table->index('status');
            $table->index(['course_id', 'status']);
            $table->index('group_id');
        });

        // Indexes لجدول online_lectures
        Schema::table('online_lectures', function (Blueprint $table) {
            $table->index('group_id');
            $table->index('level_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الـ Indexes
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['category_id']);
            $table->dropIndex('courses_active_id_index');
        });

        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'course_id']);
            $table->dropIndex(['course_id', 'subscription_status']);
            $table->dropIndex(['subscription_status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['course_id']);
        });

        Schema::table('course_reservations', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['course_id', 'id']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['level_id']);
            $table->dropIndex(['level_id', 'course_id']);
        });

        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['course_id', 'status']);
            $table->dropIndex(['group_id']);
        });

        Schema::table('online_lectures', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropIndex(['level_id']);
        });
    }
};
