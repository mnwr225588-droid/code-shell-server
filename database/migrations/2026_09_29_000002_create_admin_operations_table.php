<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل عمليات الأدمن على حسابات الطلاب:
     * - شحن رصيد المحفظة
     * - اشتراك طالب في كورس مباشرة من لوحة الأدمن
     */
    public function up(): void
    {
        Schema::create('admin_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 20); // topup | subscribe
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['operation', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_operations');
    }
};
