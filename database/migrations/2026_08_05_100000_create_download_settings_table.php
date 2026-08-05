<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إعدادات التحميل من الموقع لكل منصة (android / windows):
     * يتحكم الأدمن في تفعيل/إيقاف زر التنزيل على موقع E:\code_shell_web.
     */
    public function up(): void
    {
        Schema::create('download_settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->unique();
            $table->boolean('download_enabled')->default(true);
            $table->timestamps();
        });

        // القيم الافتراضية: التنزيل متاح لكلا المنصتين
        DB::table('download_settings')->insert([
            ['platform' => 'android', 'download_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['platform' => 'windows', 'download_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('download_settings');
    }
};
