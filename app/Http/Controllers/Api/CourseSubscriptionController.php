<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
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
     * تسجيل اشتراك المستخدم الحالي في كورس معين
     */
    public function subscribe(Request $request, $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // ربط المستخدم بالكورس في جدول الاشتراكات دون تكرار
        $user->subscribedCourses()->syncWithoutDetaching([$courseId]);

        return response()->json([
            'status'        => true,
            'message'       => 'تم الاشتراك في الكورس بنجاح!',
            'is_subscribed' => true,
            'students_count'=> $course->subscribedUsers()->count() + 120,
        ]);
    }

    /**
     * إلغاء اشتراك المستخدم الحالي في كورس معين
     */
    public function cancel(Request $request, $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

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
