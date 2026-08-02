<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    // جلب جميع الكورسات النشطة (منشورة + قريباً) — التطبيق يحدد طريقة العرض
    public function index(Request $request)
    {
        $courses = Course::with('category')
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $courses
        ]);
    }

    // جلب كورس واحد
    public function show(Request $request, $id)
    {
        $course = Course::with('category')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $course
        ]);
    }
}
