<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تقييمات وتعليقات التطبيق من موقع E:\code_shell_web:
     * - app_ratings  : تقييم المستخدم للنسخة (من 1 إلى 5 نجوم) — تقييم واحد لكل زائر/منصة.
     * - app_comments : تعليقات الزوار على التطبيق (أكثر من تعليق مسموح).
     */
    public function up(): void
    {
        Schema::create('app_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('platform');                 // android أو windows
            $table->unsignedTinyInteger('rating');      // 1..5
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();

            // زائر واحد يعطي تقييماً واحداً فقط لكل منصة
            $table->unique(['platform', 'ip_address']);
        });

        Schema::create('app_comments', function (Blueprint $table) {
            $table->id();
            $table->string('platform');                 // android أو windows
            $table->string('name', 100);                // اسم الزائر
            $table->text('comment');                    // نص التعليق
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();

            $table->index(['platform', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_comments');
        Schema::dropIfExists('app_ratings');
    }
};