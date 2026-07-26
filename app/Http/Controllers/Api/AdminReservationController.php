<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class AdminReservationController extends Controller
{
    // جلب الكورسات مع أعداد الحجوزات
    public function getCoursesWithReservationCounts()
    {
        // the Course model already appends 'reservations_count'
        $courses = Course::all();
        return response()->json([
            'status' => true,
            'data' => $courses
        ]);
    }

    // جلب مستخدمين حجز معين مع إخفاء كلمات المرور
    public function getCourseReservations($course_id)
    {
        $course = Course::with(['reservedUsers' => function($query) {
            $query->select('users.id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'users.created_at');
        }])->findOrFail($course_id);
            
        return response()->json([
            'status' => true,
            'data' => $course->reservedUsers
        ]);
    }
}
