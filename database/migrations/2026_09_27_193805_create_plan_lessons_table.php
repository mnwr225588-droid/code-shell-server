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
        Schema::create('plan_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('course_plans')->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->foreignId('level_id')->nullable()->constrained('levels')->onDelete('cascade');
            $table->foreignId('group_id')->nullable()->constrained('course_groups')->onDelete('cascade');
            $table->boolean('is_accessible')->default(true)->comment('هل المحاضرة متاحة للمشتركين في هذه الباقة');
            $table->json('metadata')->nullable()->comment('بيانات إضافية');
            $table->timestamps();
            
            // منع تكرار نفس المحاضرة في نفس الباقة
            $table->unique(['plan_id', 'lesson_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_lessons');
    }
};
