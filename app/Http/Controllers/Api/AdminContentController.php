<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\Lesson;
use Illuminate\Http\Request;

class AdminContentController extends Controller
{
    // 1. إضافة مستوى جديد بالترتيب المحدد (1, 2, 3...)
    public function storeLevel(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title'     => 'required|string',
            'order_num' => 'required|integer',
        ]);

        $level = Level::create([
            'course_id' => $request->course_id,
            'title'     => $request->title,
            'order_num' => $request->order_num,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم إنشاء المستوى بنجاح',
            'data'    => $level
        ], 201);
    }

    // 2. إضافة درس مع الفيديو والصورة والاختبار (الأسئلة والخيارات)
    public function storeLessonWithQuiz(Request $request)
    {
        $request->validate([
            'level_id'    => 'required|exists:levels,id',
            'title'       => 'required|string',
            'description' => 'nullable|string',
            'thumbnail'   => 'nullable|image|max:2048',
            'video'       => 'required|file|mimes:mp4,mkv,avi|max:100000', // رفع فيديو حتى 100MB
        ]);

        // رفع الصورة المصغرة إذا وُجدت
        $thumbnailPath = $request->hasFile('thumbnail') 
            ? $request->file('thumbnail')->store('thumbnails', 'public') 
            : null;

        // رفع فيديو الدرس
        $videoPath = $request->file('video')->store('videos', 'public');

        // حفظ بيانات الدرس
        $lesson = Lesson::create([
            'level_id'    => $request->level_id,
            'title'       => $request->title,
            'description' => $request->description,
            'thumbnail'   => $thumbnailPath,
            'video_url'   => $videoPath,
        ]);

        // حفظ الأسئلة والاختيارات إن وُجدت
        if ($request->has('questions')) {
            $questionsData = is_string($request->questions) 
                ? json_decode($request->questions, true) 
                : $request->questions;

            foreach ($questionsData as $q) {
                $question = $lesson->questions()->create([
                    'question_text' => $q['question_text'],
                ]);

                foreach ($q['options'] as $opt) {
                    $question->options()->create([
                        'option_text' => $opt['option_text'],
                        'is_correct'  => $opt['is_correct'] ?? false,
                    ]);
                }
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم حفظ الدرس والأسئلة بنجاح!',
            'data'    => $lesson->load('questions.options')
        ], 201);
    }
}