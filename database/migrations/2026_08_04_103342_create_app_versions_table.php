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
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // لتحديد المنصة مثل: android أو windows
            $table->integer('version_code'); // رقم تسلسلي للإصدار مثل: 1, 2, 3
            $table->string('version_name'); // اسم الإصدار مثل: 1.0.0
            $table->string('file_path'); // مسار ملف التطبيق على السيرفر
            $table->text('changelog')->nullable(); // ما الجديد في هذا التحديث
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};