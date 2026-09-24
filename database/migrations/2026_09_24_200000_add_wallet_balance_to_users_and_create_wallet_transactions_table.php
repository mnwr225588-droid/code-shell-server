<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'wallet_balance')) {
                    $table->decimal('wallet_balance', 12, 2)->default(0.00)->after('fcm_token');
                }
            });
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type', 30); // topup, course_purchase, course_refund
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('reference_id', 255)->nullable(); // transaction_id or course_id
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'wallet_balance')) {
                    $table->dropColumn('wallet_balance');
                }
            });
        }
    }
};
