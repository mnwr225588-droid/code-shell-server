<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    // جلب الكورسات المنشورة فقط (is_active و is_coming_soon=false)
    public function index(Request $request)
    {
        $courses = Course::with('category')
            ->where('is_active', true)
            ->where('is_coming_soon', false)
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
