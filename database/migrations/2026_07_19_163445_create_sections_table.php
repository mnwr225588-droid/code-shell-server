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
        Schema::create('sections', function (Blueprint $table) {

            $table->id();

            // الكورس الذي ينتمي إليه هذا القسم
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            // اسم القسم
            $table->string('title');

            // وصف القسم
            $table->text('description')->nullable();

            // ترتيب القسم داخل الكورس
            $table->unsignedInteger('sort_order')->default(0);

            // هل القسم ظاهر للمستخدم؟
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};