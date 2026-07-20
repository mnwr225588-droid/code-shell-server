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
        Schema::create('courses', function (Blueprint $table) {

            $table->id();

            // اسم الكورس
            $table->string('title');

            // وصف مختصر
            $table->text('description')->nullable();

            // صورة الكورس
            $table->string('thumbnail')->nullable();

            // مجاني أم مدفوع
            $table->boolean('is_free')->default(true);

            // سعر الكورس
            $table->decimal('price', 10, 2)->default(0);

            // هل الكورس ظاهر للمستخدم؟
            $table->boolean('is_active')->default(true);

            // ترتيب العرض داخل التطبيق
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};