<?php

namespace App\Http\Controllers\Api;

/// TeacherController
/// الـ API الخاص بالمدرس في تطبيق الطالب (وضع المدرس).
/// يوفر: المجموعات، الطلاب، الجلسات الأونلاين، التأجيل، والبريك.

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\CourseGroup;
use App\Models\OnlineLecture;
use App\Models\LectureAttendance;
use App\Models\CourseSubscription;
use App\Models\LecturePostponementRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    /**
     * جلب المجموعات الخاصة بالمدرس المسجل دخوله.
     */
    public function myGroups(Request $request)
    {
        $teacher = $this->teacherAccount($request);

        $groups = CourseGroup::where('teacher_id', $teacher->id)
            ->withCount([
                'students' => fn ($query) => $query->where(function ($subscriptionQuery) {
                    $subscriptionQuery->whereNull('course_subscriptions.subscription_status')
                        ->orWhere('course_subscriptions.subscription_status', CourseSubscription::STATUS_ACTIVE);
                }),
                'onlineLectures',
            ])
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
        $teacher = $this->teacherAccount($request);

        // التأكد أن المجموعة تخص المدرس
        $group = CourseGroup::where('id', $groupId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $students = $this->activeGroupStudents($group, [
            'users.id',
            'users.first_name',
            'users.middle_name',
            'users.last_name',
            'users.name',
            'users.phone',
            'users.email',
            'users.birth_date',
        ])->get();

        return response()->json([
            'status' => true,
            'data' => $students,
        ]);
    }

    /**
     * جلب المحاضرات الأونلاين الخاصة بمجموعة معينة للمدرس.
     */
    public function groupLectures(Request $request, $groupId)
    {
        $teacher = $this->teacherAccount($request);

        $lectures = OnlineLecture::where('group_id', $groupId)
            ->where('teacher_id', $teacher->id)
            ->with(['course:id,title'])
            ->orderBy('start_date_time', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $lectures,
        ]);
    }

    public function groupAttendance(Request $request, $groupId)
    {
        $teacher = $this->teacherAccount($request);
        $group = CourseGroup::where('id', $groupId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $request->validate([
            'lecture_id' => 'required|integer',
        ]);

        $lecture = OnlineLecture::where('id', $request->input('lecture_id'))
            ->where('group_id', $group->id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $students = $this->activeGroupStudents($group, [
            'users.id',
            'users.first_name',
            'users.middle_name',
            'users.last_name',
            'users.name',
        ])->get();

        $attendance = LectureAttendance::where('online_lecture_id', $lecture->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->pluck('attendance_status', 'user_id');

        $students->each(function ($student) use ($attendance) {
            $student->attendance_status = $attendance[$student->id] ?? null;
        });

        return response()->json([
            'status' => true,
            'data' => [
                'lecture' => $lecture,
                'students' => $students,
            ],
        ]);
    }

    public function saveGroupAttendance(Request $request, $groupId)
    {
        $request->validate([
            'lecture_id' => 'required|integer',
            'attendance' => 'required|array|min:1',
            'attendance.*.user_id' => 'required|integer|distinct',
            'attendance.*.status' => 'required|in:present,absent',
        ]);

        $teacher = $this->teacherAccount($request);
        $group = CourseGroup::where('id', $groupId)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();
        $lecture = OnlineLecture::where('id', $request->input('lecture_id'))
            ->where('group_id', $group->id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $studentIds = $this->activeGroupStudents($group, ['users.id'])
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $submittedStudentIds = collect($request->input('attendance'))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (array_diff($studentIds, $submittedStudentIds)) {
            return response()->json([
                'status' => false,
                'message' => 'يرجى تحديد حالة الحضور أو الغياب لجميع طلاب المجموعة.',
            ], 422);
        }

        if (array_diff($submittedStudentIds, $studentIds)) {
            return response()->json([
                'status' => false,
                'message' => 'يوجد طالب لا ينتمي إلى هذه المجموعة.',
            ], 422);
        }

        DB::transaction(function () use ($request, $lecture) {
            foreach ($request->input('attendance') as $record) {
                $attendance = LectureAttendance::firstOrNew([
                    'online_lecture_id' => $lecture->id,
                    'user_id' => (int) $record['user_id'],
                ]);
                $attendance->attendance_status = $record['status'];
                $attendance->join_time ??= now();
                $attendance->save();
            }
        });

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ الحضور والغياب بنجاح.',
        ]);
    }

    private function activeGroupStudents(CourseGroup $group, array $columns)
    {
        $activeStudentIds = CourseSubscription::query()
            ->select('user_id')
            ->where('group_id', $group->id)
            ->where(function ($query) {
                $query->where('subscription_status', CourseSubscription::STATUS_ACTIVE)
                    ->orWhereNull('subscription_status');
            });

        return $group->students()
            ->whereIn('users.id', $activeStudentIds)
            ->select($columns);
    }

    private function teacherAccount(Request $request): Teacher
    {
        $authenticatedAccount = $request->user();
        abort_unless($authenticatedAccount, 401);

        if ($authenticatedAccount instanceof Teacher) {
            return $authenticatedAccount;
        }

        return Teacher::where('email', $authenticatedAccount->email)->firstOrFail();
    }

    /**
     * جلب الجلسات الأونلاين الخاصة بالمدرس.
     */
    public function mySessions(Request $request)
    {
        $teacher = $this->teacherAccount($request);

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
        $teacher = $this->teacherAccount($request);

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

        $teacher = $this->teacherAccount($request);

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
            'break_minutes' => 'nullable|integer|min:1|max:60',
        ]);

        $teacher = $this->teacherAccount($request);

        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $lecture->status = 'break';
        $lecture->save();

        $breakMinutes = $request->break_minutes ?: 15;

        // إرسال إشعار لطلاب المجموعة بأنه في بريك
        try {
            $groupStudents = $lecture->group?->students ?? [];
            foreach ($groupStudents as $student) {
                if ($student->fcm_token) {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $student->fcm_token)
                        ->withNotification(\Kreait\Firebase\Messaging\Notification::create(
                            'استراحة ☕',
                            "المحاضرة في استراحة لمدة {$breakMinutes} دقيقة"
                        ))
                        ->withData([
                            'type' => 'lecture_break',
                            'lecture_id' => (string) $lecture->id,
                            'break_minutes' => (string) $breakMinutes,
                        ]);
                    app('firebase.messaging')->send($message);
                }
            }
        } catch (\Throwable $e) {
            Log::error('FCM break notification error: ' . $e->getMessage());
        }

        return response()->json([
            'status' => true,
            'message' => "تم بدء الاستراحة بنجاح.",
            'break_minutes' => $breakMinutes,
            'data' => $lecture,
        ]);
    }

    /**
     * إنهاء الاستراحة واستئناف المحاضرة برابط زوم جديد.
     */
    public function endBreak(Request $request)
    {
        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
        ]);

        $teacher = $this->teacherAccount($request);

        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $zoomLinkUpdated = false;
        try {
            $zoomService = new \App\Services\ZoomService();
            $newMeeting = $zoomService->createMeeting([
                'topic' => $lecture->title . ' (استئناف بعد البريك)',
                'start_time' => now()->format('Y-m-d\TH:i:s\Z'),
                'duration' => $lecture->duration_minutes ?: 60,
                'agenda' => $lecture->description ?? '',
            ]);

            if ($newMeeting && isset($newMeeting['id'])) {
                $lecture->zoom_meeting_id = (string) $newMeeting['id'];
                $lecture->zoom_join_url = $newMeeting['join_url'];
                $lecture->zoom_start_url = $newMeeting['start_url'] ?? $newMeeting['join_url'];
                $zoomLinkUpdated = true;
            }
        } catch (\Throwable $e) {
            Log::warning('Zoom meeting re-creation failed after break: ' . $e->getMessage());
        }

        $lecture->status = 'live';
        $lecture->save();

        return response()->json([
            'status' => true,
            'message' => $zoomLinkUpdated
                ? 'تم إنهاء الاستراحة واستئناف المحاضرة برابط جديد بنجاح.'
                : 'تم إنهاء الاستراحة واستئناف المحاضرة باستخدام الرابط المحفوظ.',
            'zoom_link_updated' => $zoomLinkUpdated,
            'data' => $lecture,
        ]);
    }

    /**
     * حفظ وتحديث رابط اجتماع زوم الخاص بك للمحاضرة وإرساله للطلاب
     */
    public function updateZoomLink(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => true,
                'message' => 'Zoom link update route active',
            ]);
        }

        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
            'zoom_join_url' => 'required|string',
        ]);

        $teacher = $this->teacherAccount($request);

        $lecture = null;
        if ($teacher && isset($teacher->id)) {
            $lecture = OnlineLecture::where('id', $request->online_lecture_id)
                ->where(function($q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id)
                      ->orWhereNull('teacher_id');
                })->first();
        }

        if (!$lecture) {
            $lecture = OnlineLecture::where('id', $request->online_lecture_id)->firstOrFail();
        }

        $url = trim($request->zoom_join_url);
        $lecture->zoom_join_url = $url;
        $lecture->zoom_start_url = $url;
        if (\Illuminate\Support\Facades\Schema::hasColumn('online_lectures', 'is_custom_link')) {
            $lecture->is_custom_link = 1;
        }

        // استخراج معرف الاجتماع ID من الرابط إن وجد
        if (preg_match('/\/j\/(\d+)/i', $url, $m)) {
            $lecture->zoom_meeting_id = $m[1];
        } elseif (preg_match('/(\d{9,11})/', $url, $m)) {
            $lecture->zoom_meeting_id = $m[1];
        }

        $lecture->save();

        return response()->json([
            'status' => true,
            'message' => 'تم حفظ وإرسال رابط زوم للطلاب بنجاح.',
            'data' => $lecture,
        ]);
    }

    /**
     * بدء المحاضرة الأونلاين (تحويل الحالة إلى جاري الآن لتفعيل زر الانضمام للطلاب).
     */
    public function startLecture(Request $request)
    {
        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
            'zoom_join_url' => 'nullable|string',
        ]);

        $teacher = $this->teacherAccount($request);

        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        if ($request->filled('zoom_join_url')) {
            $url = trim($request->zoom_join_url);
            $lecture->zoom_join_url = $url;
            $lecture->zoom_start_url = $url;
            if (preg_match('/\/j\/(\d+)/i', $url, $m)) {
                $lecture->zoom_meeting_id = $m[1];
            } elseif (preg_match('/(\d{9,11})/', $url, $m)) {
                $lecture->zoom_meeting_id = $m[1];
            }
        }

        $lecture->status = 'live';
        $lecture->save();

        return response()->json([
            'status' => true,
            'message' => 'تم بدء المحاضرة وتفعيل زر الانضمام للطلاب بنجاح.',
            'data' => $lecture,
        ]);
    }

    /**
     * إنهاء المحاضرة الأونلاين.
     */
    public function endLecture(Request $request)
    {
        $request->validate([
            'online_lecture_id' => 'required|exists:online_lectures,id',
        ]);

        $teacher = $this->teacherAccount($request);

        $lecture = OnlineLecture::where('id', $request->online_lecture_id)
            ->where('teacher_id', $teacher->id)
            ->firstOrFail();

        $lecture->status = 'ended';
        $lecture->save();

        return response()->json([
            'status' => true,
            'message' => 'تم إنهاء المحاضرة بنجاح.',
            'data' => $lecture,
        ]);
    }
}
