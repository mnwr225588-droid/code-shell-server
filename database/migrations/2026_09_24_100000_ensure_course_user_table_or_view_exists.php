<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to ensure course_user is accessible.
     */
    public function up(): void
    {
        if (!Schema::hasTable('course_user')) {
            try {
                // محاولة إنشاء View في PostgreSQL أو MySQL لربط course_user بـ course_subscriptions
                DB::statement('CREATE VIEW course_user AS SELECT id, user_id, course_id, group_id, created_at, updated_at FROM course_subscriptions;');
            } catch (\Throwable $e) {
                // إذا فشل إنشاء ה-View (مثلاً صلاحيات)، أنشئ جدول course_user كـ Pivot Table احتياطي
                Schema::create('course_user', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                    $table->foreignId('group_id')->nullable();
                    $table->timestamps();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP VIEW IF EXISTS course_user;');
        } catch (\Throwable $e) {}
        Schema::dropIfExists('course_user');
    }
};
