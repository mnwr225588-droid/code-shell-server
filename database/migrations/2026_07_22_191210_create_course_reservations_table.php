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
        Schema::create('course_reservations', function (Blueprint $table) {
            $table->id();
            
            // ربط الحجز بالمستخدم (إذا تم حذف المستخدم تُحذف حجوزاته)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // ربط الحجز بالكورس (إذا تم حذف الكورس تُحذف حجوزاته)
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            
            $table->timestamps();

            // قيد منع التكرار: لا يمكن للمستخدم حجز الكورس نفسه أكثر من مرة
            $table->unique(['user_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_reservations');
    }
};