<?php

/**
 * ====================================================
 * اسم الملف: CourseController.php
 * المسار: app/Http/Controllers/Api/CourseController.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف مسؤول عن جلب بيانات الكورسات، المستويات، والدروس، والمجموعات والمحاضرات للطلاب في تطبيق الموبايل وموقع الويب.
 * 
 * وظائف الملف التفصيلية:
 * 1. index: عرض كل الكورسات المتاحة والقادمة قريباً مرتبة.
 * 2. show: جلب تفاصيل كورس معين بواسطة ID.
 * 3. getGroups: جلب المجموعات الدراسية التابعة لكورس معين.
 * 4. getLevels: جلب المستويات والدروس الخاصة بالطالب مع قفل المحتوى إذا لم يمتلك اشتراكاً نَشِطاً.
 * 5. getLessonsForLevel: جلب دروس مستوى محدد بحماية الاشتراك.
 * ====================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /// API: GET /api/courses
    public function index(Request $request)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $courses = Course::with(['category', 'groups' => function ($q) {
                $q->whereIn('status', ['open_for_registration', 'waiting_for_students'])
                  ->withCount('students');
            }])
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get();

        $courses->transform(function ($course) use ($user) {
            $data = $course->toArray();
            $data['is_subscribed'] = $user ? $course->isUserSubscribed($user->id) : false;
            return $data;
        });

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

        $user = auth('sanctum')->user() ?: $request->user();
        $courseData = $course->toArray();
        $courseData['is_subscribed'] = $user ? $course->isUserSubscribed($user->id) : false;

        return response()->json([
            'status' => true,
            'data'   => $courseData
        ]);
    }

    // جلب المجموعات المتاحة لكورس معين
    public function getGroups($courseId)
    {
        $groups = \App\Models\CourseGroup::where('course_id', $courseId)
            ->with(['onlineLectures.teacher', 'teacher'])
            ->withCount('students')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $groups
        ]);
    }

    // جلب المستويات، الدروس، والمحاضرات للكورس مع الحماية السيرفرية الصارمة
    public function getLevels(Request $request, $course_id)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user?->isAdmin() ?? false;
        $course = Course::findOrFail($course_id);

        // 🔒 حماية أمنية صارمة: يمنع منعيًا إرجاع المحتوى لمستخدم غير مسجل الدخول أو لا يملك اشتراكاً فعالاً
        if (!$isAdmin && (!$user || !$course->isUserSubscribed($userId))) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، لا يمكنك الوصول لمحتويات هذا الكورس بدون اشتراك مؤكد وفعال.',
            ], 403);
        }

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

        // إذا كان اليوزر مسجل في مجموعة غير مفعلة بعد من الأدمن
        if ($userId && !$isAdmin && $groupStatus && $groupStatus !== 'active') {
            return response()->json([
                'status'       => true,
                'data'         => [],
                'is_waiting'   => true,
                'group_status' => $groupStatus,
                'message'      => 'المجموعة قيد الانتظار، وسيتم فتح المحتوى والدروس فور تفعيل المجموعة من قبل الأدمن.',
            ]);
        }

        // إتاحة الوصول للمستويات والدروس عند تفعيل المجموعة والاشتراك
        $levels = \App\Models\Level::where('course_id', $course_id)
            ->orderBy('order_num', 'asc')
            ->with(['lessons' => function($q) {
                $q->orderBy('order_num', 'asc')->with('questions.options');
            }])
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
            'status'       => true,
            'data'         => $levels,
            'is_waiting'   => false,
            'group_status' => $groupStatus,
        ]);
    }

    // جلب الدروس لمستوى معين محمي بالاشتراك
    public function getLessonsForLevel(Request $request, $level_id)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user?->isAdmin() ?? false;

        $level = \App\Models\Level::findOrFail($level_id);
        $course = \App\Models\Course::findOrFail($level->course_id);

        // 🔒 حماية أمنية صارمة: يمنع الوصول لدروس المستوى دون اشتراك فعال
        if (!$isAdmin && (!$user || !$course->isUserSubscribed($userId))) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، يجب وجود اشتراك مؤكد وفعال للوصول إلى دروس هذا المستوى.',
            ], 403);
        }

        $lessons = \App\Models\Lesson::with('questions.options')
            ->where('level_id', $level_id)
            ->orderBy('order_num', 'asc')
            ->get();
            
        return response()->json([
            'status' => true,
            'data'   => $lessons
        ]);
    }

    // جلب المحاضرات المباشرة الخاصة بكورس معين
    public function getCourseOnlineLectures(Request $request, $course_id)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user?->isAdmin() ?? false;
        $course = Course::findOrFail($course_id);

        // 🔒 حماية أمنية: يمنع الحصول على محاضرات الكورس دون اشتراك
        if (!$isAdmin && (!$user || !$course->isUserSubscribed($userId))) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، يجب وجود اشتراك مؤكد وفعال للوصول للمحاضرات المباشرة.',
            ], 403);
        }

        $groupId = null;
        if ($userId && !$isAdmin) {
            $subscription = \DB::table('course_subscriptions')
                ->where('user_id', $userId)
                ->where('course_id', $course_id)
                ->first();
            if ($subscription && $subscription->group_id) {
                $groupId = $subscription->group_id;
            }
        }

        $query = \App\Models\OnlineLecture::where('course_id', $course_id)
            ->with(['teacher', 'group', 'level']);

        if (!$isAdmin && $groupId) {
            $query->where('group_id', $groupId);
        }

        $lectures = $query->orderBy('start_date_time', 'asc')->get();

        return response()->json([
            'status' => true,
            'data'   => $lectures
        ]);
    }
}
