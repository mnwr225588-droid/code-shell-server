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
        Schema::table('levels', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('order_num');
        });
        
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('order_num');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('is_optional');
        });
        
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('is_optional');
        });
    }
};
