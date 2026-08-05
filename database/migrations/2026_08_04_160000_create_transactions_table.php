<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول المعاملات المالية: يسجل كل عملية دفع مع بيانات بوابة الدفع
     * كاملة (payload) للمراجعة، وحالة المعاملة (قيد الانتظار/مكتمل/
     * فشل/مسترجع). الفهرس الفريد على gateway_transaction_id يضمن
     * منع تكرار المعالجة (Idempotency) عند تكرار الـ Webhook.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 10);
            $table->string('payment_gateway', 50);
            $table->string('gateway_transaction_id', 255)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
            $table->index('status');
            $table->unique('gateway_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
