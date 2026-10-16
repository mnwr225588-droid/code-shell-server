<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CourseGroup;
use App\Models\OnlineLecture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    public function groups(Request $request)
    {
        $this->ensureAdmin($request);

        $groups = CourseGroup::query()
            ->select('course_groups.*')
            ->with(['course:id,title', 'teacher:id,name'])
            ->selectSub(
                DB::table('course_subscriptions')
                    ->selectRaw('COUNT(DISTINCT user_id)')
                    ->whereColumn('course_subscriptions.group_id', 'course_groups.id')
                    ->where(function ($query) {
                        $query->whereNull('subscription_status')
                            ->orWhere('subscription_status', 'active');
                    }),
                'students_count'
            )
            ->withCount([
                'onlineLectures as ended_lectures_count' => fn ($query) => $query->where('status', 'ended'),
            ])
            ->orderByDesc('id')
            ->paginate(60);

        return response()->json([
            'status' => true,
            'data' => $groups->items(),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    public function endedLectures(Request $request, int $groupId)
    {
        $this->ensureAdmin($request);

        $group = CourseGroup::with('course:id,title')->findOrFail($groupId);
        $lectures = OnlineLecture::query()
            ->where('group_id', $group->id)
            ->where('status', 'ended')
            ->withCount('attendances')
            ->orderByDesc('start_date_time')
            ->get(['id', 'course_id', 'group_id', 'title', 'start_date_time', 'duration_minutes', 'status']);

        return response()->json([
            'status' => true,
            'group' => $group,
            'data' => $lectures,
        ]);
    }

    public function lectureAttendance(Request $request, int $lectureId)
    {
        $this->ensureAdmin($request);

        $lecture = OnlineLecture::query()
            ->where('status', 'ended')
            ->with(['group:id,name,course_id', 'course:id,title'])
            ->findOrFail($lectureId);

        $students = DB::table('course_subscriptions as subscriptions')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('lecture_attendances as attendance', function ($join) use ($lecture) {
                $join->on('attendance.user_id', '=', 'subscriptions.user_id')
                    ->where('attendance.online_lecture_id', '=', $lecture->id);
            })
            ->where('subscriptions.group_id', $lecture->group_id)
            ->where(function ($query) {
                $query->whereNull('subscriptions.subscription_status')
                    ->orWhere('subscriptions.subscription_status', 'active');
            })
            ->distinct()
            ->orderBy('users.first_name')
            ->orderBy('users.middle_name')
            ->orderBy('users.last_name')
            ->orderBy('users.id')
            ->get([
                'users.id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'users.email',
                'attendance.attendance_status',
                'attendance.updated_at as attendance_updated_at',
            ])
            ->map(function ($student) {
                $student->full_name = trim(implode(' ', array_filter([
                    $student->first_name,
                    $student->middle_name,
                    $student->last_name,
                ])));

                return $student;
            });

        return response()->json([
            'status' => true,
            'lecture' => $lecture,
            'data' => $students,
            'summary' => [
                'students' => $students->count(),
                'present' => $students->where('attendance_status', 'present')->count(),
                'absent' => $students->where('attendance_status', 'absent')->count(),
                'unmarked' => $students->whereNull('attendance_status')->count(),
            ],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        $isAdmin = $user instanceof Admin || ($user && method_exists($user, 'isAdmin') && $user->isAdmin());

        abort_unless($isAdmin, 403, 'هذه الصفحة مخصصة للإدارة.');
    }
}
