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
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'duration')) {
                $table->string('duration')->nullable();
            }
            if (!Schema::hasColumn('courses', 'difficulty')) {
                $table->string('difficulty')->nullable();
            }
            if (!Schema::hasColumn('courses', 'features')) {
                $table->json('features')->nullable();
            }
            if (!Schema::hasColumn('courses', 'what_will_learn')) {
                $table->json('what_will_learn')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['duration', 'difficulty', 'features', 'what_will_learn']);
        });
    }
};
