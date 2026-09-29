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
use App\Models\CoursePlan;
use App\Models\CourseSubscription;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CourseSubscriptionController extends Controller
{
    /**
     * جلب حالة اشتراك المستخدم الحالي في كورس معين
     */
    /**
     * جلب حالة اشتراك المستخدم الحالي في كورس معين
     * يرجع معلومات شاملة عن الاشتراك والمجموعة والباقة والمدة المتبقية
     */
    public function getStatus(Request $request, $courseId): JsonResponse
    {
        // جلب الكورس والمستخدم بأمان
        $course = Course::findCourseSafely($courseId);
        $user = $request->user();

        // التحقق من حالة الاشتراك
        $isSubscribed = $user ? $course->isUserSubscribed($user->id) : false;

        $group = null;
        $subscription = null;
        $plan = null;
        
        // إذا كان المستخدم مشترك، جلب تفاصيل الاشتراك
        if ($isSubscribed && $user) {
            // جلب الاشتراك مع الباقة المرتبطة
            $subscription = CourseSubscription::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->with('plan')
                ->first();

            // التحقق من حالة الانتهاء وتحديثها إذا لزم الأمر
            if ($subscription) {
                $subscription->checkAndUpdateExpiry();
                $plan = $subscription->plan;
            }

            // جلب المجموعة المرتبطة بالاشتراك
            if ($subscription && $subscription->group_id) {
                $group = \App\Models\CourseGroup::find($subscription->group_id);
            }

            // إذا لم تكن هناك مجموعة، تعيين واحدة تلقائياً
            if (!$group) {
                $group = \App\Services\CourseGroupService::assignStudentToOpenGroup($user, $course->id);
            }
        }

        // حساب تاريخ الاشتراك لتحديد إمكانية الإلغاء
        $subDate = $subscription ? ($subscription->paid_at ?? $subscription->created_at) : null;
        $canCancel = $subDate ? ($subDate->diffInHours(now()) <= 48) : true;

        // حساب المدة المتبقية من الاشتراك
        $remainingDays = 0;
        $expiryDate = null;
        $startedAt = null;
        $durationDays = 0;
        $firstLectureDate = null;
        
        if ($subscription) {
            $startedAt = $subscription->started_at ? $subscription->started_at->toIso8601String() : null;
            
            // حساب مدة الباقة
            if ($plan) {
                $durationDays = $plan->duration_days;
            }
            
            if ($subscription->expired_at) {
                $remainingDays = $subscription->remaining_days;
                $expiryDate = $subscription->expired_at->toIso8601String();
            }
            
            // جلب تاريخ أول محاضرة أونلاين للكورس
            $firstLecture = \DB::table('online_lectures')
                ->where('course_id', $course->id)
                ->orderBy('created_at', 'asc')
                ->first();
            
            if ($firstLecture) {
                $firstLectureDate = $firstLecture->created_at;
            }
        }

        // جلب المعومات الدقيقة للمجموعة
        $groupStudentsCount = 0;
        $groupCapacity = 10;
        $groupStatus = 'open_for_registration';
        $isWaitingCompletion = false;
        
        if ($group) {
            $groupStudentsCount = \DB::table('course_subscriptions')
                ->where('group_id', $group->id)
                ->count();
            $groupCapacity = $group->capacity ?: 10;
            $groupStatus = $group->status;
            $isWaitingCompletion = in_array($groupStatus, ['open_for_registration', 'waiting_for_students']);
        }

        $openGroup = \App\Models\CourseGroup::where('course_id', $course->id)
            ->whereIn('status', ['open_for_registration', 'waiting_for_students'])
            ->first();

        // إرجاع الاستجابة بجميع المعلومات المطلوبة
        return response()->json([
            'status'          => true,
            'is_subscribed'   => $isSubscribed,
            'has_open_groups' => (bool) $openGroup || (bool) $group,
            'group'           => $group ? [
                'id'                    => $group->id,
                'name'                  => $group->name,
                'status'                => $groupStatus,
                'students_count'        => $groupStudentsCount,
                'capacity'              => $groupCapacity,
                'capacity_text'         => "{$groupStudentsCount} من {$groupCapacity} طلاب",
                'is_waiting_completion' => $isWaitingCompletion,
                'completion_notice'     => 'سوف يتم تفعيل وبدء الكورس عند اكتمال العدد المطلوب في المجموعة',
            ] : null,
            'group_name'                 => $group ? $group->name : null,
            'students_count'             => $groupStudentsCount,
            'group_capacity'             => $groupCapacity,
            'group_capacity_text'        => "{$groupStudentsCount} من {$groupCapacity} طلاب",
            'is_waiting_group_completion'=> $isWaitingCompletion,
            'subscribed_at'              => $subDate ? $subDate->toIso8601String() : null,
            'can_cancel'                 => $canCancel,
            'hours_since_subscription'   => $subDate ? $subDate->diffInHours(now()) : 0,
            'plan'                       => $plan ? [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'duration_days' => $plan->duration_days,
                'duration_text' => $plan->duration_text,
            ] : null,
            'remaining_days'             => $remainingDays,
            'expired_at'                 => $expiryDate,
            'started_at'                 => $startedAt,
            'subscription_duration_days' => $durationDays,
            'first_lecture_date'         => $firstLectureDate,
            'is_expired'                 => $subscription ? $subscription->is_expired : false,
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
    /**
     * تسجيل اشتراك المستخدم الحالي في كورس معين
     * يدعم نظام الباقات (Plans) للتحكم في مدة الاشتراك والسعر
     */
    public function subscribe(Request $request, $courseId): JsonResponse
    {
        // جلب الكورس والمستخدم بأمان
        $course = Course::findCourseSafely($courseId);
        $user = $request->user();

        // الحصول على نوع الخطة من الطلب (monthly أو term_3months)
        // القيمة الافتراضية هي monthly إذا لم يتم تحديدها
        $planType = $request->input('plan_type', 'monthly');

        // البحث عن الباقة المناسبة للكورس
        $plan = CoursePlan::where('course_id', $courseId)
            ->where(function($q) use ($planType, $courseId) {
                $q->where('slug', $planType)
                  ->orWhere('slug', "course_{$courseId}_{$planType}");
            })
            ->where('is_active', true)
            ->first();

        // إذا لم توجد باقة، نحاول إنشاء واحدة افتراضية تلقائياً بـ slug فريد للكورس
        if (!$plan) {
            $uniqueSlug = "course_{$courseId}_{$planType}";
            $plan = CoursePlan::updateOrCreate(
                [
                    'course_id' => $courseId,
                    'slug'      => $uniqueSlug,
                ],
                [
                    'name'          => $planType === 'term_3months' ? 'اشتراك ثلاث شهور' : 'اشتراك شهري',
                    'price'         => $course->price ?: 100,
                    'currency'      => 'EGP',
                    'duration_days' => $planType === 'term_3months' ? 90 : 30,
                    'is_active'     => true,
                ]
            );
        }

        // التحقق من توفر مجموعة غير مفعلة للتسجيل
        // المجموعات التي ليست active أو completed هي المتاحة
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
        // الكورسات المدفوعة تتطلب دفع مكتمل أولاً
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

        // إذا لم يتم تعيين مجموعة، نحاول إنشاء واحدة
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
        }

        // إنشاء أو تحديث الاشتراك مع ربطه بالباقة المحددة
        // updateOrCreate يضمن عدم تكرار الاشتراك لنفس المستخدم والكورس
        $subscription = CourseSubscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $courseId,
            ],
            [
                'group_id' => $assignedGroup->id,
                'plan_id' => $plan->id,
                'subscription_status' => CourseSubscription::STATUS_ACTIVE,
                'payment_status' => $course->is_free ? CourseSubscription::PAYMENT_NOT_REQUIRED : CourseSubscription::PAYMENT_PAID,
                'amount' => $plan->price,
                'currency_code' => $plan->currency,
                'course_price_at_purchase' => $course->price,
                'metadata' => json_encode(['plan_type' => $planType]),
                'is_expired' => false,
            ]
        );

        // إرجاع استجابة النجاح مع معلومات الباقة
        return response()->json([
            'status'        => true,
            'message'       => 'تم الاشتراك في الكورس بنجاح!',
            'is_subscribed' => true,
            'students_count'=> $course->subscribedUsers()->count() + 120,
            'plan'          => [
                'id' => $plan->id,
                'name' => $plan->name,
                'duration_days' => $plan->duration_days,
                'duration_text' => $plan->duration_text,
            ],
        ]);
    }

    /**
     * إلغاء اشتراك المستخدم في كورس مع إرجاع كامل المبلغ إلى محفظته تلقائياً.
     */
    public function cancel(Request $request, $courseId): JsonResponse
    {
        $course = Course::findCourseSafely($courseId);
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

        // فحص مهلة الـ يومان (48 ساعة) للاسترجاع وإلغاء الاشتراك
        $subDate = $subscription ? ($subscription->paid_at ?? $subscription->created_at) : null;
        if ($subDate && $subDate->diffInHours(now()) > 48) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، لا يمكن إلغاء الاشتراك بعد مرور أكثر من يومين (48 ساعة) على تاريخ الاشتراك.',
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

            // 1. حذف سجل الاشتراك كلياً من السيرفر
            if ($subscription) {
                $subscription->delete();
            }

            \DB::table('course_subscriptions')
                ->where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->delete();

            if (method_exists($user, 'subscribedCourses')) {
                $user->subscribedCourses()->detach($courseId);
            }

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
                    'description'   => "استرداد قيمة كورس ({$course->title}) بعد إلغاء الاشتراك وحذفه كلياً",
                ]);
            }

            \DB::commit();

            $msg = $refundAmount > 0 
                ? "تم إلغاء الاشتراك وحذف الكورس كلياً وإضافة {$refundAmount} ج.م إلى محفظتك!"
                : "تم إلغاء الاشتراك وحذف الكورس كلياً من حسابك!";

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
