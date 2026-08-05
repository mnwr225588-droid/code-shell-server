<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة عمود الدولة (country) لجدول المستخدمين.
     * يُحفظ اسم الدولة أو رمزها (مثل "مصر" أو "Egypt" أو "+20")
     * ويُرسل من التطبيق أثناء التسجيل أو تسجيل الدخول.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country', 100)->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
