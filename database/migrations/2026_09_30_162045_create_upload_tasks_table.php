<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إنشاء جدول مهام الرفع (Upload Tasks)
     * لتتبع عمليات الرفع المجزأ وعرضها في صفحة مهام الرفع
     */
    public function up(): void
    {
        Schema::create('upload_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id')->unique()->comment('معرف فريد للرفع');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete()->comment('معرف الأدمن الذي قام بالرفع');
            $table->string('task_type')->default('lesson')->comment('نوع المهمة: lesson, app_version, etc.');
            $table->string('filename')->nullable()->comment('اسم الملف');
            $table->integer('total_chunks')->default(0)->comment('إجمالي عدد المقاطع');
            $table->integer('received_chunks')->default(0)->comment('عدد المقاطع المستلمة');
            $table->integer('progress')->default(0)->comment('نسبة التقدم (0-100)');
            $table->enum('status', ['pending', 'uploading', 'completed', 'failed', 'cancelled'])->default('pending')->comment('حالة الرفع');
            $table->string('error_message')->nullable()->comment('رسالة الخطأ في حالة الفشل');
            $table->timestamp('started_at')->nullable()->comment('وقت بدء الرفع');
            $table->timestamp('completed_at')->nullable()->comment('وقت انتهاء الرفع');
            $table->timestamps();
            
            $table->index('upload_id');
            $table->index('admin_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_tasks');
    }
};
