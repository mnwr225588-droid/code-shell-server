<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة أعمدة المزامنة السحابية الكاملة لجدول التقدم:
     * - exam_scores: درجات الاختبارات لكل درس (JSON) — كانت تُرسل من التطبيق
     *   وتُهمل من السيرفر، لذلك كانت تختفي بعد تسجيل الدخول مرة أخرى.
     * - last_video_second / last_video_lesson: موضع آخر فيديو شاهده المستخدم.
     */
    public function up(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            $table->json('exam_scores')->nullable()->after('completed');
            $table->unsignedInteger('last_video_second')->default(0)->after('exam_scores');
            $table->string('last_video_lesson', 255)->nullable()->after('last_video_second');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('progress', function (Blueprint $table) {
            $table->dropColumn(['exam_scores', 'last_video_second', 'last_video_lesson']);
        });
    }
};
