<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // تحديث الكورس الشهري ليكون مدته 30 يوم
        DB::table('courses')
            ->where('title', 'منهج البرمجة - اشتراك شهري')
            ->update(['subscription_duration_days' => 30]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إعادة تعيين للقيمة الافتراضية
        DB::table('courses')
            ->where('title', 'منهج البرمجة - اشتراك شهري')
            ->update(['subscription_duration_days' => 90]);
    }
};
