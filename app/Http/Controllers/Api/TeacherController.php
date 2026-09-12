<?php

namespace App\Http\Controllers\Api;

/// TeacherController
/// الـ API الخاص بالمدرس في تطبيق الطالب (وضع المدرس).
/// يوفر: المجموعات، الطلاب، الجلسات الأونلاين، التأجيل، والبريك.

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\CourseGroup;
use App\Models\OnlineLecture;
use App\Models\LecturePostponementRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    /**
     * جلب المجموعات الخاصة بالمدرس المسجل دخوله.
     */
    public function myGroups(Request $request)
    {
        $teacher = $request->user();

        $groups = CourseGroup::where('teacher_id', $teacher->id)
            ->withCount('students')
            ->with('course:id,title,thumbnail')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $groups,
        ]);
    }

    /**
     * جلب طلاب مجموعة معينة (الاسم، الهاتف، تاريخ الميلاد فقط).
     */
    public function groupStudents(Request $request, $groupId)
    {
        $teacher = $request->user();

        // التأكد أن المجموعة تخص المدرس
        $group = CourseGroup::where('id', $groupId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $students = $group->students()
            ->select('users.id', 'users.first_name', 'users.middle_name', 'users.last_name', 'users.name', 'users.phone', 'users.birth_date')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $students,
        ]);
    }

    /**
     * جلب الجلسات الأونلاين الخاصة بالمدرس.
     */
    public function mySessions(Request $request)
    {
        $teacher = $request->user();

        $lectures = OnlineLecture::where('teacher_id', $teacher->id)
            ->with(['group:id,name', 'course:id,title'])
            ->orderBy('start_date_time', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $lectures,
        ]);
    }

    /**
     * تفاصيل جلسة معينة.
     */
    public function sessionDetails(Request $request, $id)
    {
        $teacher = $request->user();

        $lecture = OnlineLecture::where('id', $id)
            ->where('teacher_id', $teacher->id)
            ->with(['group:id,name', 'course:id,title'])
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $lecture,
        ]);
    }

    /**
     * إرسال طلب تأجيل محاضرة.
     */
    public function requestPostponement(Request $request)
    {
        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
            'reason' => 'required|string|max:500',
            'new_date' => 'required|date|after_or_equal:today',
            'new_time' => 'required|date_format:H:i',
        ]);

        $teacher = $request->user();

        // التأكد أن المحاضرة تخص المدرس
        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $postponement = LecturePostponementRequest::create([
            'online_lecture_id' => $lecture->id,
            'teacher_id' => $teacher->id,
            'reason' => $request->reason,
            'new_date' => $request->new_date,
            'new_time' => $request->new_time,
            'status' => 'pending',
        ]);

        // إرسال إشعار للأدمن
        try {
            $admins = \App\Models\User::where('is_admin', true)->get();
            foreach ($admins as $admin) {
                if ($admin->fcm_token) {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $admin->fcm_token)
                        ->withNotification(\Kreait\Firebase\Messaging\Notification::create(
                            'طلب تأجيل محاضرة 📋',
                            "المدرس {$teacher->name} يطلب تأجيل محاضرة: {$lecture->title}"
                        ))
                        ->withData([
                            'type' => 'postponement_request',
                            'postponement_id' => (string) $postponement->id,
                        ]);
                    app('firebase.messaging')->send($message);
                }
            }
        } catch (\Throwable $e) {
            Log::error('FCM postponement notification error: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إرسال طلب التأجيل بنجاح. في انتظار موافقة الإدارة.',
            'data' => $postponement,
        ]);
    }

    /**
     * إعطاء بريك (استراحة) أثناء المحاضرة.
     * يتم تحديث حالة المحاضرة مؤقتاً وإرسال إشعار للطلاب.
     */
    public function startBreak(Request $request)
    {
        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
            'break_minutes' => 'required|integer|min:1|max:30',
        ]);

        $teacher = $request->user();

        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        // إرسال إشعار لطلاب المجموعة بأنه في بريك
        try {
            $groupStudents = $lecture->group->students;
            foreach ($groupStudents as $student) {
                if ($student->fcm_token) {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $student->fcm_token)
                        ->withNotification(\Kreait\Firebase\Messaging\Notification::create(
                            'استراحة ☕',
                            "المحاضرة في استراحة لمدة {$request->break_minutes} دقيقة"
                        ))
                        ->withData([
                            'type' => 'lecture_break',
                            'lecture_id' => (string) $lecture->id,
                            'break_minutes' => (string) $request->break_minutes,
                        ]);
                    app('firebase.messaging')->send($message);
                }
            }
        } catch (\Throwable $e) {
            Log::error('FCM break notification error: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => "تم بدء استراحة {$request->break_minutes} دقيقة.",
            'break_minutes' => $request->break_minutes,
        ]);
    }

    /**
     * بدء المحاضرة الأونلاين (تحويل الحالة إلى جاري الآن).
     */
    public function startLecture(Request $request)
    {
        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
        ]);

        $teacher = $request->user();

        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $lecture->status = 'live';
        $lecture->save();

        return response()->json([
            'status' => true,
            'message' => 'تم بدء المحاضرة بنجاح.',
            'data' => $lecture,
        ]);
    }
}
