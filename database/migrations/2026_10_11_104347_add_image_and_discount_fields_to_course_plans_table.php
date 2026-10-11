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
        Schema::table('course_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('course_plans', 'image')) {
                $table->string('image')->nullable()->after('description');
            }
            if (!Schema::hasColumn('course_plans', 'original_price')) {
                $table->decimal('original_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('course_plans', 'discount_price')) {
                $table->decimal('discount_price', 10, 2)->nullable()->after('original_price');
            }
            if (!Schema::hasColumn('course_plans', 'has_discount')) {
                $table->boolean('has_discount')->default(false)->after('discount_price');
            }
            if (!Schema::hasColumn('course_plans', 'discount_percentage')) {
                $table->integer('discount_percentage')->nullable()->after('has_discount');
            }
            if (!Schema::hasColumn('course_plans', 'original_prices')) {
                $table->json('original_prices')->nullable()->after('prices');
            }
            if (!Schema::hasColumn('course_plans', 'discount_prices')) {
                $table->json('discount_prices')->nullable()->after('original_prices');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_plans', function (Blueprint $table) {
            $table->dropColumn([
                'image',
                'original_price',
                'discount_price',
                'has_discount',
                'discount_percentage',
                'original_prices',
                'discount_prices',
            ]);
        });
    }
};
