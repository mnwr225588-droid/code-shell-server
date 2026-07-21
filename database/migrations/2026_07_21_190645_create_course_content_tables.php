<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جدول المستويات (مرتبط بجداول الكورسات/اللغات)
        if (!Schema::hasTable('levels')) {
            Schema::create('levels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->nullable()->constrained('courses')->onDelete('cascade');
                $table->string('title'); // عنوان المستوى (مثل: المستوى الأول)
                $table->integer('order_num')->default(1); // رقم الترتيب (1, 2, 3...)
                $table->timestamps();
            });
        }

        // 2. جدول الدروس (مرتبط بالمستوى)
        if (!Schema::hasTable('lessons')) {
            Schema::create('lessons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('level_id')->constrained('levels')->onDelete('cascade');
                $table->string('title'); // عنوان الدرس
                $table->text('description')->nullable(); // وصف الدرس
                $table->string('thumbnail')->nullable(); // صورة مصغرة
                $table->string('video_url'); // رابط أو مسار الفيديو
                $table->timestamps();
            });
        }

        // 3. جدول الأسئلة (مرتبط بالدرس)
        if (!Schema::hasTable('questions')) {
            Schema::create('questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
                $table->text('question_text'); // نص السؤال
                $table->timestamps();
            });
        }

        // 4. جدول الخيارات (مرتبط بالسؤال)
        if (!Schema::hasTable('options')) {
            Schema::create('options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
                $table->string('option_text'); // نص الخيار
                $table->boolean('is_correct')->default(false); // هل هو الإجابة الصحيحة؟
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('levels');
    }
};