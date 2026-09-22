<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حالة 'break' (استراحة) لعمود status في جدول المحاضرات الأونلاين
     */
    public function up(): void
    {
        // PostgreSQL: alter enum type to add 'break' value
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Check if 'break' already exists in the enum
            $exists = DB::select("SELECT 1 FROM pg_enum WHERE enumlabel = 'break' AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'online_lectures_status_check' OR typname LIKE '%status%' LIMIT 1) LIMIT 1");
            if (empty($exists)) {
                DB::statement("ALTER TABLE online_lectures DROP CONSTRAINT IF EXISTS online_lectures_status_check");
                DB::statement("ALTER TABLE online_lectures ADD CONSTRAINT online_lectures_status_check CHECK (status::text = ANY (ARRAY['scheduled'::text, 'live'::text, 'ended'::text, 'cancelled'::text, 'break'::text]))");
            }
        } else {
            // MySQL: modify column to include 'break'
            DB::statement("ALTER TABLE online_lectures MODIFY COLUMN status ENUM('scheduled', 'live', 'ended', 'cancelled', 'break') DEFAULT 'scheduled'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE online_lectures DROP CONSTRAINT IF EXISTS online_lectures_status_check");
            DB::statement("ALTER TABLE online_lectures ADD CONSTRAINT online_lectures_status_check CHECK (status::text = ANY (ARRAY['scheduled'::text, 'live'::text, 'ended'::text, 'cancelled'::text]))");
        } else {
            DB::statement("ALTER TABLE online_lectures MODIFY COLUMN status ENUM('scheduled', 'live', 'ended', 'cancelled') DEFAULT 'scheduled'");
        }
    }
};
