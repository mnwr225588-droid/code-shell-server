<?php

/**
 * ====================================================
 * اسم الملف: 2026_09_24_000000_update_course_subscriptions_and_transactions_for_easykash.php
 * المسار: database/migrations/2026_09_24_000000_update_course_subscriptions_and_transactions_for_easykash.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذه التحديثة (Migration) مسؤولة عن توسيع جدول الاشتراكات (course_subscriptions)
 * بشكل آمن ودون حذف أو تعديل أي بيانات سابقة موجودة في قاعدة البيانات.
 * 
 * الأعمدة المضافة:
 * - subscription_status: حالة الاشتراك (active, pending, cancelled, expired)
 * - payment_status: حالة الدفع (paid, pending, failed, not_required)
 * - amount & currency_code: المبلغ والعملة وقت الشراء
 * - course_price_at_purchase: تثبيت لقطة (Snapshot) لسعر الكورس وقت الدفع
 * - payment_gateway & gateway_transaction_id: بيانات البوابة والمعاملة
 * - paid_at, failed_at, cancelled_at: تواريخ تغير حالة الاشتراك
 * - user_course_sub_status_idx: فهرس مركّب لتسريع استعلامات التحقق من الاشتراك
 * ====================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تنفيذ التعديلات الآمنة على قاعدة البيانات
     */
    public function up(): void
    {
        if (Schema::hasTable('course_subscriptions')) {
            Schema::table('course_subscriptions', function (Blueprint $table) {
                // حالة الاشتراك (نشط / معلق / ملغى / منتهي)
                if (!Schema::hasColumn('course_subscriptions', 'subscription_status')) {
                    $table->string('subscription_status', 30)->nullable()->after('group_id');
                }

                // حالة الدفع المالية (مدفوع / قيد الانتظار / فشل / غير مطلوب)
                if (!Schema::hasColumn('course_subscriptions', 'payment_status')) {
                    $table->string('payment_status', 30)->nullable()->after('subscription_status');
                }

                // المبلغ والعملة المدفوعة
                if (!Schema::hasColumn('course_subscriptions', 'amount')) {
                    $table->decimal('amount', 12, 2)->nullable()->after('payment_status');
                }
                if (!Schema::hasColumn('course_subscriptions', 'currency_code')) {
                    $table->string('currency_code', 10)->nullable()->after('amount');
                }

                // سعر الكورس وقت الشراء
                if (!Schema::hasColumn('course_subscriptions', 'course_price_at_purchase')) {
                    $table->decimal('course_price_at_purchase', 12, 2)->nullable()->after('currency_code');
                }

                // اسم بوابة الدفع والمعاملة
                if (!Schema::hasColumn('course_subscriptions', 'payment_gateway')) {
                    $table->string('payment_gateway', 50)->nullable()->after('course_price_at_purchase');
                }
                if (!Schema::hasColumn('course_subscriptions', 'gateway_transaction_id')) {
                    $table->string('gateway_transaction_id', 255)->nullable()->after('payment_gateway');
                }
                if (!Schema::hasColumn('course_subscriptions', 'gateway_reference_id')) {
                    $table->string('gateway_reference_id', 255)->nullable()->after('gateway_transaction_id');
                }

                // مفتاح منع التكرار والتواريخ
                if (!Schema::hasColumn('course_subscriptions', 'idempotency_key')) {
                    $table->string('idempotency_key', 255)->nullable()->after('gateway_reference_id');
                }
                if (!Schema::hasColumn('course_subscriptions', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('idempotency_key');
                }
                if (!Schema::hasColumn('course_subscriptions', 'failed_at')) {
                    $table->timestamp('failed_at')->nullable()->after('paid_at');
                }
                if (!Schema::hasColumn('course_subscriptions', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('failed_at');
                }
                if (!Schema::hasColumn('course_subscriptions', 'expired_at')) {
                    $table->timestamp('expired_at')->nullable()->after('cancelled_at');
                }

                // سبب الفشل والبيانات الإضافية (Metadata)
                if (!Schema::hasColumn('course_subscriptions', 'failure_reason')) {
                    $table->text('failure_reason')->nullable()->after('expired_at');
                }
                if (!Schema::hasColumn('course_subscriptions', 'metadata')) {
                    $table->json('metadata')->nullable()->after('failure_reason');
                }

                // إضافة الفهرس المركب لتحسين الأداء
                $table->index(['user_id', 'course_id', 'subscription_status'], 'user_course_sub_status_idx');
            });
        }
    }

    /**
     * إلغاء التعديلات عند التراجع عن الـ Migration
     */
    public function down(): void
    {
        if (Schema::hasTable('course_subscriptions')) {
            Schema::table('course_subscriptions', function (Blueprint $table) {
                $table->dropIndex('user_course_sub_status_idx');
                $table->dropColumn([
                    'subscription_status',
                    'payment_status',
                    'amount',
                    'currency_code',
                    'course_price_at_purchase',
                    'payment_gateway',
                    'gateway_transaction_id',
                    'gateway_reference_id',
                    'idempotency_key',
                    'paid_at',
                    'failed_at',
                    'cancelled_at',
                    'expired_at',
                    'failure_reason',
                    'metadata',
                ]);
            });
        }
    }
};
