<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS course_subscriptions_user_id_course_id_unique');
            DB::statement(
                'CREATE UNIQUE INDEX course_subscriptions_user_course_plan_unique ' .
                'ON course_subscriptions (user_id, course_id, plan_id)'
            );
            return;
        }

        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->dropUnique('course_subscriptions_user_id_course_id_unique');
            $table->unique(
                ['user_id', 'course_id', 'plan_id'],
                'course_subscriptions_user_course_plan_unique'
            );
        });

    }

    public function down(): void
    {
        $hasMultipleSubscriptions = DB::table('course_subscriptions')
            ->select('user_id', 'course_id')
            ->groupBy('user_id', 'course_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleSubscriptions) {
            throw new RuntimeException(
                'Cannot restore the one-subscription-per-course constraint while users have multiple plan subscriptions.'
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS course_subscriptions_user_course_plan_unique');
            DB::statement(
                'CREATE UNIQUE INDEX course_subscriptions_user_id_course_id_unique ' .
                'ON course_subscriptions (user_id, course_id)'
            );
            return;
        }

        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->dropUnique('course_subscriptions_user_course_plan_unique');
            $table->unique(['user_id', 'course_id']);
        });

    }
};
