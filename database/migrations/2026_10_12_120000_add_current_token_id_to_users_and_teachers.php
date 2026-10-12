<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'current_token_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('current_token_id')->nullable();
            });
        }
        if (Schema::hasTable('teachers') && !Schema::hasColumn('teachers', 'current_token_id')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->unsignedBigInteger('current_token_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'current_token_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('current_token_id');
            });
        }
        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'current_token_id')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->dropColumn('current_token_id');
            });
        }
    }
};
