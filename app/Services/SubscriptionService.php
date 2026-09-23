<?php

/**
 * ====================================================
 * اسم الملف: SubscriptionService.php
 * المسار: app/Services/SubscriptionService.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف مسؤول عن منطق العمليات الخاص بالاشتراكات (Business Logic Layer for Subscriptions).
 * يفصل منطق منح الاشتراكات وتأكيد الدفع عن الـ Controllers لمنع التكرار والحفاظ على الأمان.
 * 
 * وظائف الملف التفصيلية:
 * 1. canAccessCourse: فحص ما إذا كان المستخدم يملك صلاحية الوصول للكورس (أدمن أو اشتراك نشط).
 * 2. createFreeSubscription: تفعيل اشتراك مباشر للكورسات المجانية دون المرور ببوابة الدفع.
 * 3. initiatePaymentSubscription: إنشاء سجل اشتراك جديد للكورسات المدفوعة بحالة معلقة (pending).
 * 4. activateFromTransaction: تفعيل الاشتراك والمعاملة بعد التأكيد السيرفري الناجح مع قفل الصفوف لمنع سباق التحديثات.
 * 5. markFailedFromTransaction: تسجيل فشل العملية وإبقاء الكورس غير متاح للطالب.
 * ====================================================
 */

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * هل المستخدم يملك اشتراكاً نشطاً أو صلاحية أدمن للكورس؟
     * 
     * @param User|null $user المستخدم الحالي
     * @param Course $course الكورس المطلوب
     * @return bool true إذا كان يملك وصولاً مسموحاً
     */
    public function canAccessCourse(?User $user, Course $course): bool
    {
        if (!$user) {
            return false;
        }

        // الأدمن يملك وصولاً كاملاً لكل الكورسات دون الحاجة لاشتراك
        if ($user->isAdmin()) {
            return true;
        }

        return $course->isUserSubscribed($user->id);
    }

    /**
     * إنشاء اشتراك لكورس مجاني (Free Course) مباشرة وبطريقة رسمية من السيرفر
     * 
     * @param User $user الطالب
     * @param Course $course الكورس المجاني
     * @return CourseSubscription سجل الاشتراك المكتمل
     */
    public function createFreeSubscription(User $user, Course $course): CourseSubscription
    {
        return DB::transaction(function () use ($user, $course) {
            $subscription = CourseSubscription::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$subscription) {
                $subscription = new CourseSubscription();
                $subscription->user_id = $user->id;
                $subscription->course_id = $course->id;
            }

            // تفعيل الاشتراك مجاناً وتحديد حالة الدفع بأنها غير مطلوبة
            $subscription->subscription_status = CourseSubscription::STATUS_ACTIVE;
            $subscription->payment_status = CourseSubscription::PAYMENT_NOT_REQUIRED;
            $subscription->amount = 0.00;
            $subscription->currency_code = 'EGP';
            $subscription->course_price_at_purchase = 0.00;
            $subscription->payment_gateway = 'free';
            $subscription->paid_at = now();
            $subscription->save();

            // تعيين الطالب لمجموعة مفتوحة تلقائياً
            CourseGroupService::assignStudentToOpenGroup($user, $course->id);

            return $subscription;
        });
    }

    /**
     * إنشاء أو تحديث سجل اشتراك جديد بكورس مدفوع بوضع معلق (Pending)
     * (لا يعطي صلاحية وصول إطلاقاً حتى يتم تأكيد الدفع سيرفرياً)
     * 
     * @param User $user الطالب
     * @param Course $course الكورس
     * @param Transaction $transaction المعاملة المالية
     * @return CourseSubscription سجل الاشتراك المعلق
     */
    public function initiatePaymentSubscription(User $user, Course $course, Transaction $transaction): CourseSubscription
    {
        return DB::transaction(function () use ($user, $course, $transaction) {
            $subscription = CourseSubscription::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$subscription) {
                $subscription = new CourseSubscription();
                $subscription->user_id = $user->id;
                $subscription->course_id = $course->id;
            }

            // إذا كان المستخدم يملك بالفعل اشتراكاً نشطاً سابقاً لا نغير حالته لـ pending
            if ($subscription->subscription_status !== CourseSubscription::STATUS_ACTIVE) {
                $subscription->subscription_status = CourseSubscription::STATUS_PENDING;
                $subscription->payment_status = CourseSubscription::PAYMENT_PENDING;
            }

            $subscription->amount = (float) $transaction->amount;
            $subscription->currency_code = $transaction->currency_code;
            $subscription->course_price_at_purchase = (float) $transaction->amount;
            $subscription->payment_gateway = $transaction->payment_gateway;
            $subscription->gateway_transaction_id = $transaction->gateway_transaction_id;
            $subscription->save();

            return $subscription;
        });
    }

    /**
     * تفعيل الاشتراك بعد التحقق السيرفري الناجح للدفع (Server-Side Payment Verification)
     * مع تطبيق قفل الصف والمحافظة على Idempotency
     * 
     * @param Transaction $transaction المعاملة المالية المحلية
     * @param array $gatewayPayload البيانات القادمة من البوابة
     * @return bool true عند نجاح التفعيل
     */
    public function activateFromTransaction(Transaction $transaction, array $gatewayPayload = []): bool
    {
        return DB::transaction(function () use ($transaction, $gatewayPayload) {
            // قفل صف المعاملة لمنع سباق التحديثات
            $lockedTx = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
            if (!$lockedTx) {
                return false;
            }

            // Idempotency: إذا كانت مكتملة سابقاً لا نكرر التفعيل
            if ($lockedTx->isCompleted()) {
                return true;
            }

            // تحديث المعاملة المالية إلى مكتملة
            $oldPayload = $lockedTx->payload ?? [];
            $lockedTx->status = Transaction::STATUS_COMPLETED;
            $lockedTx->payload = array_merge($oldPayload, $gatewayPayload);
            $lockedTx->save();

            // تحديث سجل الاشتراك وتفعيله رسمياً
            $subscription = CourseSubscription::where('user_id', $lockedTx->user_id)
                ->where('course_id', $lockedTx->course_id)
                ->lockForUpdate()
                ->first();

            if (!$subscription) {
                $subscription = new CourseSubscription();
                $subscription->user_id = $lockedTx->user_id;
                $subscription->course_id = $lockedTx->course_id;
            }

            $subscription->subscription_status = CourseSubscription::STATUS_ACTIVE;
            $subscription->payment_status = CourseSubscription::PAYMENT_PAID;
            $subscription->amount = (float) $lockedTx->amount;
            $subscription->currency_code = $lockedTx->currency_code;
            $subscription->course_price_at_purchase = (float) $lockedTx->amount;
            $subscription->payment_gateway = $lockedTx->payment_gateway;
            $subscription->gateway_transaction_id = $lockedTx->gateway_transaction_id;
            $subscription->paid_at = now();
            $subscription->save();

            // ربط المستخدم بأحدث مجموعة مفتوحة
            CourseGroupService::assignStudentToOpenGroup($lockedTx->user, $lockedTx->course_id);

            Log::info("Payment & Subscription activated successfully for User {$lockedTx->user_id}, Course {$lockedTx->course_id}, Tx #{$lockedTx->id}");

            return true;
        });
    }

    /**
     * إلغاء أو تسجيل فشل عملية الاشتراك
     * 
     * @param Transaction $transaction المعاملة المالية
     * @param string $reason سبب الفشل
     */
    public function markFailedFromTransaction(Transaction $transaction, string $reason = 'Payment failed'): void
    {
        DB::transaction(function () use ($transaction, $reason) {
            $lockedTx = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
            if ($lockedTx) {
                $lockedTx->status = Transaction::STATUS_FAILED;
                $payload = $lockedTx->payload ?? [];
                $payload['failure_reason'] = $reason;
                $lockedTx->payload = $payload;
                $lockedTx->save();
            }

            $subscription = CourseSubscription::where('user_id', $transaction->user_id)
                ->where('course_id', $transaction->course_id)
                ->first();

            if ($subscription && $subscription->subscription_status !== CourseSubscription::STATUS_ACTIVE) {
                $subscription->subscription_status = CourseSubscription::STATUS_PENDING;
                $subscription->payment_status = CourseSubscription::PAYMENT_FAILED;
                $subscription->failed_at = now();
                $subscription->failure_reason = $reason;
                $subscription->save();
            }
        });
    }
}
