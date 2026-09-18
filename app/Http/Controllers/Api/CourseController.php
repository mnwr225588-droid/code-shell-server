<?php

namespace App\Http\Controllers\Api;

/// الـ CourseController (للطالب)
/// مسؤول عن جلب الكورسات لعرضها في تطبيق الطالب (code_shell_app).
/// بيتعامل فقط مع الكورسات النشطة (is_active = true).
/// الكورسات الخاصة بالأدمن بتيجي من AdminContentController.

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /// API: GET /api/courses
    /// بيجيب كل الكورسات المنشورة (وممكن تكون قريباً is_coming_soon).
    /// بيستخدم الـ Eager Loading (with category) علشان يقلل عدد استعلامات الداتا بيز (N+1 Query Problem).
    public function index(Request $request)
    {
        $courses = Course::with(['category', 'groups' => function ($q) {
                $q->whereIn('status', ['open_for_registration', 'waiting_for_students'])
                  ->withCount('students');
            }])
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
        $course = Course::with(['category', 'groups' => function ($q) {
                $q->whereIn('status', ['open_for_registration', 'waiting_for_students'])
                  ->withCount('students');
            }])->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $course
        ]);
    }

    // جلب المجموعات المتاحة لكورس معين
    public function getGroups($courseId)
    {
        $groups = \App\Models\CourseGroup::where('course_id', $courseId)
            ->withCount('students')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $groups
        ]);
    }

    // جلب المستويات، الدروس، والمحاضرات للكورس
    public function getLevels(Request $request, $course_id)
    {
        $userId = auth('sanctum')->id();
        $isAdmin = auth('sanctum')->user()?->isAdmin() ?? false;
        $course = Course::findOrFail($course_id);

        // إذا كان يوزر عادي، نجيب الـ Group الخاص به
        $groupId = null;
        $groupStatus = null;
        if ($userId && !$isAdmin) {
            $subscription = \DB::table('course_subscriptions')
                ->where('user_id', $userId)
                ->where('course_id', $course_id)
                ->first();
            
            if ($subscription && $subscription->group_id) {
                $groupId = $subscription->group_id;
                $group = \App\Models\CourseGroup::find($groupId);
                $groupStatus = $group ? $group->status : null;
            }
        }

        // إذا كان اليوزر مسجل في مجموعة غير مفعلة بعد من الأدمن (ليست active)
        if ($userId && !$isAdmin && $groupStatus && $groupStatus !== 'active') {
            return response()->json([
                'status'       => true,
                'data'         => [],
                'is_waiting'   => true,
                'group_status' => $groupStatus,
                'message'      => 'المجموعة قيد الانتظار، وسيتم فتح المحتوى والدروس فور تفعيل المجموعة من قبل الأدمن.',
            ]);
        }

        // إتاحة الوصول للمستويات والدروس عند تفعيل المجموعة
        $levels = \App\Models\Level::where('course_id', $course_id)
            ->orderBy('order_num', 'asc')
            ->with(['lessons' => function($q) {
                $q->orderBy('order_num', 'asc')->with('questions.options');
            }])
            // نجيب المحاضرات الخاصة بهالمجموعة بس أو كلها لو أدمن
            ->with(['onlineLectures' => function($q) use ($groupId, $isAdmin) {
                if (!$isAdmin && $groupId) {
                    $q->where('group_id', $groupId);
                }
                $q->with('teacher');
            }])
            ->get();

        $completedLessonIds = [];
        if ($userId) {
            $completedLessonIds = \App\Models\LessonCompletion::where('user_id', $userId)
                ->pluck('lesson_id')
                ->toArray();
        }

        $isNextUnlocked = true;

        foreach ($levels as $level) {
            $levelLocked = true;
            foreach ($level->lessons as $lesson) {
                $lesson->is_locked = $isAdmin ? false : !$isNextUnlocked;
                if (!$lesson->is_locked) {
                    $levelLocked = false;
                }
                if (in_array($lesson->id, $completedLessonIds)) {
                    $lesson->is_completed = true;
                    $isNextUnlocked = true;
                } else {
                    $lesson->is_completed = false;
                    if (!$lesson->is_optional) {
                        $isNextUnlocked = false;
                    } else {
                        $isNextUnlocked = true;
                    }
                }
            }
            if ($level->is_optional) {
                $isNextUnlocked = true;
            }
            $level->is_locked = $isAdmin ? false : $levelLocked;
        }

        return response()->json([
            'status' => true,
            'data'   => $levels,
            'is_waiting' => false,
            'group_status' => $groupStatus,
        ]);
    }

    // جلب الدروس لمستوى معين (للوحة التحكم)
    public function getLessonsForLevel($level_id)
    {
        $lessons = \App\Models\Lesson::with('questions.options')
            ->where('level_id', $level_id)
            ->orderBy('order_num', 'asc')
            ->get();
            
        return response()->json([
            'status' => true,
            'data' => $lessons
        ]);
    }
}
