<?php

/**
 * ====================================================
 * اسم الملف: CourseSubscriptionController.php
 * المسار: app/Http/Controllers/Api/CourseSubscriptionController.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف مسؤول عن إدارة اشتراكات الطلاب في الكورسات (مجانية أو مدفوعة).
 * 
 * وظائف الملف التفصيلية:
 * 1. getStatus: الاستعلام عن حالة اشتراك الطالب الحالية في كورس معين وإرجاع عدد الطلاب.
 * 2. subscribe: تسجيل اشتراك الطالب في كورس مجاني أو مدفوع (بعد التأكد من المعاملة المكتملة) وتعيينه لمجموعة مفتوحة.
 * 3. cancel: إلغاء اشتراك الطالب في الكورس مع تطبيق قواعد الأمان.
 * ====================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseSubscriptionController extends Controller
{
    /**
     * جلب حالة اشتراك المستخدم الحالي في كورس معين
     */
    public function getStatus(Request $request, $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        $isSubscribed = $user ? $course->isUserSubscribed($user->id) : false;

        $group = null;
        if ($isSubscribed && $user) {
            $subscription = \App\Models\CourseSubscription::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
            
            if ($subscription && $subscription->group_id) {
                $group = \App\Models\CourseGroup::find($subscription->group_id);
            }

            if (!$group) {
                $group = \App\Services\CourseGroupService::assignStudentToOpenGroup($user, $course->id);
            }
        }

        return response()->json([
            'status'        => true,
            'is_subscribed' => $isSubscribed,
            'group'         => $group ? [
                'id'   => $group->id,
                'name' => $group->name,
            ] : null,
            'group_name'    => $group ? $group->name : null,
            'students_count'=> $course->subscribedUsers()->count() + 120,
        ]);
    }

    /**
     * تسجيل اشتراك المستخدم الحالي في كورس معين.
     *
     * أمان: الكورسات المدفوعة لا يمكن الاشتراك فيها مباشرة —
     * يجب أن تكون هناك معاملة دفع مكتملة (أُنشئت فقط عبر Webhook
     * بعد تحقق السيرفر المستقل من البوابة). الكورسات المجانية فقط
     * يُسمح لها بالاشتراك المباشر من هذا المسار.
     */
    public function subscribe(Request $request, $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // التحقق من توفر مجموعة غير مفعلة للتسجيل
        $availableGroup = \App\Models\CourseGroup::where('course_id', $courseId)
            ->whereNotIn('status', ['active', 'completed'])
            ->exists();

        if (!$availableGroup) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، لا توجد مجموعات متاحة للتسجيل حالياً في هذا الكورس. المجموعات الحالية مفعلة بالكامل أو غير متوفرة.',
            ], 422);
        }

        // ══════════════════════════════════════════════════════
        // 🔒 حماية: منع الاشتراك المباشر في الكورسات المدفوعة
        // ══════════════════════════════════════════════════════
        if (!$course->is_free) {
            $hasCompletedPayment = Transaction::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->exists();

            if (!$hasCompletedPayment) {
                return response()->json([
                    'status'  => false,
                    'message' => 'يجب إتمام الدفع أولاً للاشتراك في هذا الكورس.',
                ], 403);
            }
        }

        // ربط المستخدم بأحدث مجموعة مفتوحة، أو إنشاء مجموعة أساسية تلقائياً
        $assignedGroup = \App\Services\CourseGroupService::assignStudentToOpenGroup($user, $courseId);

        if (!$assignedGroup) {
            $assignedGroup = \App\Models\CourseGroup::where('course_id', $courseId)->first();
            if (!$assignedGroup) {
                $assignedGroup = \App\Models\CourseGroup::create([
                    'course_id' => $courseId,
                    'name'      => 'المجموعة الأولى (الأساسية)',
                    'capacity'  => 100,
                    'status'    => 'active'
                ]);
            }
            $user->subscribedCourses()->syncWithoutDetaching([
                $courseId => ['group_id' => $assignedGroup->id]
            ]);
        }

        return response()->json([
            'status'        => true,
            'message'       => 'تم الاشتراك في الكورس بنجاح!',
            'is_subscribed' => true,
            'students_count'=> $course->subscribedUsers()->count() + 120,
        ]);
    }

    /**
     * إلغاء اشتراك المستخدم في كورس مع إرجاع كامل المبلغ إلى محفظته تلقائياً.
     */
    public function cancel(Request $request, $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // التأكد من أن المستخدم مشترك حالياً في الكورس
        $subscription = \App\Models\CourseSubscription::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->first();

        if (!$subscription && !$course->isUserSubscribed($user->id)) {
            return response()->json([
                'status'  => false,
                'message' => 'أنت غير مشترك في هذا الكورس بالأساس.',
            ], 422);
        }

        \DB::beginTransaction();
        try {
            // احتساب المبلغ المسترد
            $refundAmount = 0.00;
            if ($subscription && (float)$subscription->amount > 0) {
                $refundAmount = (float) $subscription->amount;
            } elseif (!$course->is_free) {
                $refundAmount = (float) $course->price;
            }

            // 1. تحديث حالة الاشتراك إلى ملغى
            if ($subscription) {
                $subscription->subscription_status = \App\Models\CourseSubscription::STATUS_CANCELLED;
                $subscription->payment_status = \App\Models\CourseSubscription::PAYMENT_CANCELLED;
                $subscription->cancelled_at = now();
                $subscription->save();
            }

            \DB::table('course_subscriptions')
                ->where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->update([
                    'subscription_status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            // 2. إذا كان الكورس مدفوعاً، أضف المبلغ المحسوب إلى محفظة المستخدم
            if ($refundAmount > 0) {
                $user->wallet_balance = (float) $user->wallet_balance + $refundAmount;
                $user->save();

                \App\Models\WalletTransaction::create([
                    'user_id'       => $user->id,
                    'type'          => 'course_refund',
                    'amount'        => $refundAmount,
                    'balance_after' => (float) $user->wallet_balance,
                    'reference_id'  => (string) $course->id,
                    'description'   => "استرداد قيمة كورس ({$course->title}) بعد إلغاء الاشتراك",
                ]);
            }

            \DB::commit();

            $msg = $refundAmount > 0 
                ? "تم إلغاء الاشتراك بنجاح وإضافة {$refundAmount} ج.م إلى محفظتك!"
                : "تم إلغاء الاشتراك في الكورس بنجاح!";

            return response()->json([
                'status'         => true,
                'success'        => true,
                'message'        => $msg,
                'refund_amount'  => $refundAmount,
                'wallet_balance' => (float) $user->wallet_balance,
                'is_subscribed'  => false,
                'students_count' => max(0, $course->subscribedUsers()->count() + 120),
            ]);

        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ أثناء إلغاء الاشتراك: ' . $e->getMessage(),
            ], 500);
        }
    }
}
