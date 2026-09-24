<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هذا الـ Migration يجعل عمود course_id في جدول transactions قابلاً للـ null.
 * السبب: معاملات شحن المحفظة (Wallet Top-up) ليست مرتبطة بأي كورس،
 * لذا يجب أن يسمح العمود بالقيمة null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // إزالة القيد القديم (Foreign Key) ثم إعادة تعريفه كـ nullable
            $table->foreignId('course_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }
};
