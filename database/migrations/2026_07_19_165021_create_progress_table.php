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
        Schema::create('progress', function (Blueprint $table) {

            $table->id();

            // المستخدم
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // الكورس
            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            // آخر درس وصل إليه
            $table->unsignedInteger('last_lesson')->default(1);

            // نسبة الإنجاز
            $table->decimal('progress_percentage', 5, 2)->default(0);

            // هل أنهى الكورس؟
            $table->boolean('completed')->default(false);

            $table->timestamps();

            // لا يمكن أن يوجد سجلان لنفس المستخدم في نفس الكورس
            $table->unique(['user_id', 'course_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress');
    }
};