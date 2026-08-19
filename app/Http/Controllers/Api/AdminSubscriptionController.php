<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    // جلب الكورسات مع أعداد المشتركين
    public function getCoursesWithSubscriptionCounts()
    {
        // Course model appends 'subscriptions_count'
        $courses = Course::all();
        return response()->json([
            'status' => true,
            'data' => $courses
        ]);
    }

    // جلب مستخدمي اشتراك معين مع إخفاء كلمات المرور
    public function getCourseSubscriptions($course_id)
    {
        $course = Course::with(['subscribedUsers' => function($query) {
            $query->select('users.id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'users.created_at');
        }])->findOrFail($course_id);

        return response()->json([
            'status' => true,
            'data' => $course->subscribedUsers
        ]);
    }
}