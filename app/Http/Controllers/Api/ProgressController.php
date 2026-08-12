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
        // Ensure the built-in Computer Fundamentals course exists in DB to pass validation
        if (in_array($request->course_id, [1, 11])) {
            $category = \App\Models\Category::firstOrCreate(
                ['id' => 1],
                ['name' => 'كورسات أساسية']
            );
            \App\Models\Course::firstOrCreate(
                ['id' => $request->course_id],
                [
                    'title' => 'أساسيات الحاسوب',
                    'category_id' => $category->id,
                    'description' => 'كورس أساسيات الحاسوب (مدمج في التطبيق)',
                    'is_free' => true,
                    'price' => 0,
                    'is_active' => true,
                ]
            );
        }

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'last_lesson' => 'required|string',
            'progress_percentage' => 'required|numeric|min:0|max:100',
            'completed' => 'required|boolean',
            'exam_scores' => 'nullable|json',
            'last_video_second' => 'nullable|integer|min:0',
            'last_video_lesson' => 'nullable|string|max:255',
        ]);

        // درجات الاختبارات تُرسل من التطبيق كنص JSON — تحويلها لخريطة
        // ليُخزنها Eloquent بشكل صحيح في عمود JSON.
        $examScores = null;
        if (!empty($validated['exam_scores'])) {
            $examScores = is_string($validated['exam_scores'])
                ? json_decode($validated['exam_scores'], true)
                : $validated['exam_scores'];
        }

        // user_id يُؤخذ حصراً من المستخدم المصادق عليه عبر التوكن
        // ولا يُقبل أبداً من بيانات يرسلها العميل.
        $progress = Progress::updateOrCreate(
            [
                'user_id' => auth('sanctum')->id(),
                'course_id' => $validated['course_id'],
            ],
            [
                'last_lesson' => $validated['last_lesson'],
                'progress_percentage' => $validated['progress_percentage'],
                'completed' => $validated['completed'],
                'exam_scores' => $examScores,
                'last_video_second' => $validated['last_video_second'] ?? 0,
                'last_video_lesson' => $validated['last_video_lesson'] ?? null,
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
        // جلب حصري عبر user_id الخاص بالمستخدم المسجل حالياً فقط
        $progress = Progress::where('user_id', auth('sanctum')->id())
            ->get();

        return response()->json([
            'success' => true,
            'progress' => $progress,
        ]);
    }

    /**
     * تعليم الدرس كمكتمل لفك قفل الدرس التالي
     */
    public function markLessonComplete($lesson_id): JsonResponse
    {
        \App\Models\LessonCompletion::firstOrCreate([
            'user_id' => auth()->id(),
            'lesson_id' => $lesson_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تعليم الدرس كمكتمل بنجاح.',
        ]);
    }
}