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

        return response()->json([
            'status'        => true,
            'is_subscribed' => $isSubscribed,
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

        // ربط المستخدم بأحدث مجموعة مفتوحة، والتحقق من اكتمال العدد
        $assignedGroup = \App\Services\CourseGroupService::assignStudentToOpenGroup($user, $courseId);

        if (!$assignedGroup) {
            // إذا لم يكن هناك مجموعة، يشترك بدون مجموعة
            $user->subscribedCourses()->syncWithoutDetaching([$courseId]);
        }

        return response()->json([
            'status'        => true,
            'message'       => 'تم الاشتراك في الكورس بنجاح!',
            'is_subscribed' => true,
            'students_count'=> $course->subscribedUsers()->count() + 120,
        ]);
    }

    /**
     * إلغاء اشتراك المستخدم الحالي في كورس معين.
     *
     * أمان: الكورسات المدفوعة لا يمكن إلغاء اشتراكها عبر API مباشرة
     * لمنع إساءة الاستخدام (إلغاء ثم إعادة الاشتراك مجاناً).
     */
    public function cancel(Request $request, $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // 🔒 منع إلغاء اشتراك كورس مدفوع عبر API
        if (!$course->is_free) {
            return response()->json([
                'status'  => false,
                'message' => 'لا يمكن إلغاء اشتراك كورس مدفوع. تواصل مع الدعم.',
            ], 403);
        }

        // إزالة ربط المستخدم بالكورس من جدول الاشتراكات
        $user->subscribedCourses()->detach($courseId);

        return response()->json([
            'status'        => true,
            'success'       => true,
            'message'       => 'تم إلغاء الاشتراك في الكورس بنجاح!',
            'is_subscribed' => false,
            'students_count'=> $course->subscribedUsers()->count() + 120,
        ]);
    }
}
