<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseReservationController extends Controller
{
    /**
     * جلب حالة الحجز والعدد الحقيقي للحاجزين للكورس
     */
    public function getStatus(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // معرفة ما إذا كان المستخدم الحالي قد حجز المكان مسبقاً أم لا
        $isReserved = $user ? $course->reservedUsers()->where('user_id', $user->id)->exists() : false;

        return response()->json([
            'status'             => true,
            'reservations_count' => $course->reservedUsers()->count(),
            'is_reserved'        => $isReserved,
        ]);
    }

    /**
     * حجز مكان في الكورس أو إلغاء الحجز (Toggle)
     */
    public function toggleReservation(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        // عملية التبديل: إن كان محجوزاً يُلغى، وإن لم يكن يتم الحجز
        $changes = $user->reservedCourses()->toggle($courseId);

        $isReserved = count($changes['attached']) > 0;

        return response()->json([
            'status'             => true,
            'message'            => $isReserved ? 'تم حجز مكانك بنجاح!' : 'تم إلغاء الحجز بنجاح.',
            'is_reserved'        => $isReserved,
            'reservations_count' => $course->reservedUsers()->count(),
        ]);
    }
}