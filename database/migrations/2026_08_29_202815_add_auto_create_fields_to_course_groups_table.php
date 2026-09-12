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
            $table->integer('duration_days')->default(0)->nullable()->after('capacity');
            $table->boolean('is_auto_create')->default(false)->after('duration_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_groups', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'is_auto_create']);
        });
    }
};
