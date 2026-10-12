<?php

/**
 * Migration لإضافة Indexes لتحسين أداء استعلامات الكورسات
 * متوافق مع PostgreSQL و MySQL
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        
        // Indexes على جدول course_subscriptions
        Schema::table('course_subscriptions', function (Blueprint $table) use ($driver) {
            if (!$this->indexExists('course_subscriptions', 'idx_user_course', $driver)) {
                $table->index(['user_id', 'course_id'], 'idx_user_course');
            }
            if (!$this->indexExists('course_subscriptions', 'course_subscriptions_course_id_index', $driver)) {
                $table->index('course_id');
            }
            if (!$this->indexExists('course_subscriptions', 'idx_subscription_status', $driver)) {
                $table->index('subscription_status', 'idx_subscription_status');
            }
            if (!$this->indexExists('course_subscriptions', 'idx_group_id', $driver)) {
                $table->index('group_id', 'idx_group_id');
            }
        });

        // Indexes على جدول course_reservations
        Schema::table('course_reservations', function (Blueprint $table) use ($driver) {
            if (!$this->indexExists('course_reservations', 'course_reservations_course_id_index', $driver)) {
                $table->index('course_id');
            }
        });

        // Indexes على جدول levels
        Schema::table('levels', function (Blueprint $table) use ($driver) {
            if (!$this->indexExists('levels', 'levels_course_id_index', $driver)) {
                $table->index('course_id');
            }
            if (!$this->indexExists('levels', 'idx_plan_id', $driver)) {
                $table->index('plan_id', 'idx_plan_id');
            }
        });

        // Indexes على جدول lessons
        Schema::table('lessons', function (Blueprint $table) use ($driver) {
            if (!$this->indexExists('lessons', 'lessons_level_id_index', $driver)) {
                $table->index('level_id');
            }
        });

        // Indexes على جدول courses
        Schema::table('courses', function (Blueprint $table) use ($driver) {
            if (!$this->indexExists('courses', 'courses_is_active_index', $driver)) {
                $table->index('is_active');
            }
            if (!$this->indexExists('courses', 'courses_category_id_index', $driver)) {
                $table->index('category_id');
            }
        });

        // Indexes على جدول course_groups
        Schema::table('course_groups', function (Blueprint $table) use ($driver) {
            if (!$this->indexExists('course_groups', 'idx_course_status', $driver)) {
                $table->index(['course_id', 'status'], 'idx_course_status');
            }
        });

        // Indexes على جدول online_lectures
        if (Schema::hasTable('online_lectures')) {
            Schema::table('online_lectures', function (Blueprint $table) use ($driver) {
                if (!$this->indexExists('online_lectures', 'idx_ol_course_id', $driver)) {
                    $table->index('course_id', 'idx_ol_course_id');
                }
                if (!$this->indexExists('online_lectures', 'idx_ol_group_id', $driver)) {
                    $table->index('group_id', 'idx_ol_group_id');
                }
                if (!$this->indexExists('online_lectures', 'idx_ol_level_id', $driver)) {
                    $table->index('level_id', 'idx_ol_level_id');
                }
                if (!$this->indexExists('online_lectures', 'idx_ol_plan_id', $driver)) {
                    $table->index('plan_id', 'idx_ol_plan_id');
                }
            });
        }

        // Indexes على جدول lesson_completions
        if (Schema::hasTable('lesson_completions')) {
            Schema::table('lesson_completions', function (Blueprint $table) use ($driver) {
                if (!$this->indexExists('lesson_completions', 'idx_user_lesson', $driver)) {
                    $table->index(['user_id', 'lesson_id'], 'idx_user_lesson');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_user_course');
            $table->dropIndex('course_subscriptions_course_id_index');
            $table->dropIndex('idx_subscription_status');
            $table->dropIndex('idx_group_id');
        });

        Schema::table('course_reservations', function (Blueprint $table) {
            $table->dropIndex('course_reservations_course_id_index');
        });

        Schema::table('levels', function (Blueprint $table) {
            $table->dropIndex('levels_course_id_index');
            $table->dropIndex('idx_plan_id');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex('lessons_level_id_index');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_is_active_index');
            $table->dropIndex('courses_category_id_index');
        });

        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropIndex('idx_course_status');
        });

        if (Schema::hasTable('online_lectures')) {
            Schema::table('online_lectures', function (Blueprint $table) {
                $table->dropIndex('idx_ol_course_id');
                $table->dropIndex('idx_ol_group_id');
                $table->dropIndex('idx_ol_level_id');
                $table->dropIndex('idx_ol_plan_id');
            });
        }

        if (Schema::hasTable('lesson_completions')) {
            Schema::table('lesson_completions', function (Blueprint $table) {
                $table->dropIndex('idx_user_lesson');
            });
        }
    }

    /**
     * التحقق من وجود index على جدول معين (متوافق مع PostgreSQL و MySQL)
     */
    private function indexExists(string $table, string $index, string $driver): bool
    {
        try {
            if ($driver === 'pgsql') {
                // PostgreSQL
                $query = "SELECT COUNT(*) as count 
                          FROM pg_indexes 
                          WHERE schemaname = 'public' 
                          AND tablename = ? 
                          AND indexname = ?";
                
                $result = DB::select($query, [$table, $index]);
                return $result[0]->count > 0;
            } else {
                // MySQL
                $dbName = DB::connection()->getDatabaseName();
                $query = "SELECT COUNT(*) as count 
                          FROM information_schema.statistics 
                          WHERE table_schema = ? 
                          AND table_name = ? 
                          AND index_name = ?";
                
                $result = DB::select($query, [$dbName, $table, $index]);
                return $result[0]->count > 0;
            }
        } catch (\Exception $e) {
            // في حالة أي خطأ، نفترض أن الـ index غير موجود
            return false;
        }
    }
};
