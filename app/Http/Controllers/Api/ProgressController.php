<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Progress;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgressController extends Controller
{
    /**
     * حفظ تقدم المستخدم
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'last_lesson' => 'required|integer|min:1',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'completed' => 'required|boolean',
        ]);

        $progress = Progress::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'course_id' => $validated['course_id'],
            ],
            [
                'last_lesson' => $validated['last_lesson'],
                'progress_percentage' => $validated['progress_percentage'],
                'completed' => $validated['completed'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ التقدم بنجاح.',
            'progress' => $progress,
        ]);
    }

    /**
     * جلب تقدم المستخدم
     */
    public function index(): JsonResponse
    {
        $progress = Progress::where('user_id', auth()->id())
            ->get();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }
}