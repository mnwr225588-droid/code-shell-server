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
        Schema::table('online_lectures', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            
            $table->foreign('teacher_id')
                  ->references('id')
                  ->on('teachers')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_lectures', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            
            $table->foreign('teacher_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }
};
