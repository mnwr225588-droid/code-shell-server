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
        Schema::table('online_lectures', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->unsignedBigInteger('level_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_lectures', function (Blueprint $table) {
            $table->unsignedBigInteger('level_id')->nullable(false)->change();
            $table->foreign('level_id')->references('id')->on('levels')->onDelete('cascade');
        });
    }
};
