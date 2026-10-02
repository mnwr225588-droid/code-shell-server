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
        Schema::table('course_groups', function (Blueprint $table) {
            $table->timestamp('activation_scheduled_at')->nullable()->after('status');
            $table->integer('registration_period_days')->default(7)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropColumn(['activation_scheduled_at', 'registration_period_days']);
        });
    }
};
