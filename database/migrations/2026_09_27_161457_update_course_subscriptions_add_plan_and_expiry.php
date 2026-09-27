<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained('course_plans')->onDelete('set null');
            $table->timestamp('started_at')->nullable()->comment('تاريخ بدء الاشتراك من أول محاضرة');
            // التحقق من عدم وجود expired_at قبل إضافته
            if (!Schema::hasColumn('course_subscriptions', 'expired_at')) {
                $table->timestamp('expired_at')->nullable()->comment('تاريخ انتهاء الاشتراك');
            }
            $table->boolean('is_expired')->default(false)->comment('هل الاشتراك منتهي');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'started_at', 'is_expired']);
            // لا نحذف expired_at لأنه كان موجوداً من قبل
        });
    }
};
