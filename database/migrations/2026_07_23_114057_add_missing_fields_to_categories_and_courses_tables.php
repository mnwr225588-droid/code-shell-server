<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('icon')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (!Schema::hasColumn('courses', 'category_id')) {
                    $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('courses', 'is_coming_soon')) {
                    $table->boolean('is_coming_soon')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};