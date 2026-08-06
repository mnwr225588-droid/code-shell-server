<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل إرسالات الإشعارات من لوحة الأدمن:
     * عنوان الإشعار، نصه، الصورة (إن وجدت)، نوع الاستهداف، الكورس/البريد
     * المستهدف، عدد المستخدمين الذين وصلهم الإشعار، ومَن أرسله.
     * يُقرأ من تطبيق الأدمن في شاشة "سجل الإشعارات".
     */
    public function up(): void
    {
        Schema::create('notification_sends', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('image_url')->nullable();
            $table->string('target_type'); // all | course | not_subscribed | email
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('fcm_sent')->default(0);
            $table->unsignedInteger('no_token')->default(0);
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_sends');
    }
};
