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
        Schema::create('course_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->string('name'); // اسم الباقة: شهري، ترم، إلخ
            $table->string('name_en')->nullable(); // الاسم بالإنجليزية
            $table->string('slug')->unique(); // للرابط الفريد
            $table->text('description')->nullable(); // وصف الباقة
            $table->decimal('price', 10, 2)->default(0); // السعر
            $table->string('currency', 3)->default('EGP'); // العملة
            $table->json('prices')->nullable(); // أسعار متعددة العملات
            $table->integer('duration_days')->default(30); // مدة الاشتراك بالأيام
            $table->string('duration_type')->default('days'); // days, months, years
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable(); // مميزات الباقة
            $table->json('metadata')->nullable(); // بيانات إضافية
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_plans');
    }
};
