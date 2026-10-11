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
        // 1. إضافة plan_id لجدول levels
        if (Schema::hasTable('levels') && !Schema::hasColumn('levels', 'plan_id')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('course_id');
                $table->foreign('plan_id')->references('id')->on('course_plans')->onDelete('set null');
                $table->index('plan_id');
            });
        }

        // 2. إضافة plan_id لجدول course_groups
        if (Schema::hasTable('course_groups') && !Schema::hasColumn('course_groups', 'plan_id')) {
            Schema::table('course_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('course_id');
                $table->foreign('plan_id')->references('id')->on('course_plans')->onDelete('set null');
                $table->index('plan_id');
            });
        }

        // 3. إضافة plan_id لجدول online_lectures
        if (Schema::hasTable('online_lectures') && !Schema::hasColumn('online_lectures', 'plan_id')) {
            Schema::table('online_lectures', function (Blueprint $table) {
                $table->unsignedBigInteger('plan_id')->nullable()->after('group_id');
                $table->foreign('plan_id')->references('id')->on('course_plans')->onDelete('set null');
                $table->index('plan_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('online_lectures') && Schema::hasColumn('online_lectures', 'plan_id')) {
            Schema::table('online_lectures', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
                $table->dropColumn('plan_id');
            });
        }

        if (Schema::hasTable('course_groups') && Schema::hasColumn('course_groups', 'plan_id')) {
            Schema::table('course_groups', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
                $table->dropColumn('plan_id');
            });
        }

        if (Schema::hasTable('levels') && Schema::hasColumn('levels', 'plan_id')) {
            Schema::table('levels', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
                $table->dropColumn('plan_id');
            });
        }
    }
};
