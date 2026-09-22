<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineLecture;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OnlineLectureController extends Controller
{
    public function join(Request $request, $id)
    {
        $userId = auth('sanctum')->id();
        $user = auth('sanctum')->user();
        $isAdmin = $user?->isAdmin() ?? false;

        $lecture = OnlineLecture::with('teacher')->findOrFail($id);

        // 1. Verify user's subscription (students only)
        $isTeacher = ($user && method_exists($user, 'getTable') && $user->getTable() === 'teachers') 
            || ($user && isset($user->teacher) && $user->teacher !== null) 
            || ($userId == $lecture->teacher_id);
        
        if (!$isTeacher && !$isAdmin && $user) {
            $isSubscribed = $user->subscribedCourses()->where('courses.id', $lecture->course_id)->exists()
                || \DB::table('course_subscriptions')
                    ->where('user_id', $userId)
                    ->where('course_id', $lecture->course_id)
                    ->exists();

            if (!$isSubscribed) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بدخول هذه المحاضرة. يجب الاشتراك في الكورس أولاً.'
                ], 403);
            }

            // Sync group_id if missing or update to match lecture group
            $sub = \DB::table('course_subscriptions')
                ->where('user_id', $userId)
                ->where('course_id', $lecture->course_id)
                ->first();

            if ($sub && (empty($sub->group_id) || $sub->group_id != $lecture->group_id)) {
                \DB::table('course_subscriptions')
                    ->where('user_id', $userId)
                    ->where('course_id', $lecture->course_id)
                    ->update(['group_id' => $lecture->group_id]);
            }
        }

        // 2. Verify status for students
        if (!$isAdmin && !$isTeacher) {
            if ($lecture->status === 'break') {
                return response()->json([
                    'status' => false,
                    'message' => 'المحاضرة حالياً في استراحة (بريك). يرجى الانتظار لحين استئناف المحاضرة.'
                ], 403);
            }

            if ($lecture->status === 'ended') {
                return response()->json([
                    'status' => false,
                    'message' => 'هذه المحاضرة منتهية.'
                ], 403);
            }

            if ($lecture->status !== 'live') {
                return response()->json([
                    'status' => false,
                    'message' => 'المحاضرة لم تبدأ بعد من المدرس. يرجى الانتظار حتى يقوم المدرس ببدء المحاضرة.'
                ], 403);
            }
        }

        $zoomJoinUrl = $lecture->zoom_join_url;
        if (empty($zoomJoinUrl)) {
            return response()->json([
                'status' => false,
                'message' => 'لم يقم المدرس بإضافة رابط اجتماع Zoom لهذه المحاضرة بعد. يرجى الانتظار حتى يضع المدرس الرابط.'
            ], 400);
        }

        // 4. Return appropriate URL based on user role
        if ($isAdmin || $isTeacher) {
            return response()->json([
                'status' => true,
                'message' => 'تم التحقق بنجاح',
                'data' => [
                    'role' => 'host',
                    'join_url' => $lecture->zoom_start_url ?: $zoomJoinUrl,
                    'start_url' => $lecture->zoom_start_url ?: $zoomJoinUrl,
                ]
            ]);
        } else {
            $studentName = urlencode($user?->name ?: 'طالب');
            $meetingId = $lecture->zoom_meeting_id;
            $deepLink = ($meetingId && strlen((string)$meetingId) >= 10)
                ? "zoomus://zoom.us/join?confno={$meetingId}&uname={$studentName}" 
                : $zoomJoinUrl;

            return response()->json([
                'status' => true,
                'message' => 'تم التحقق بنجاح',
                'data' => [
                    'role' => 'student',
                    'join_url' => $zoomJoinUrl,
                    'deep_link' => $deepLink,
                ]
            ]);
        }
    }

    public function myLectures(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'data' => []]);
        }

        $groupIds = $user->groups()->pluck('course_groups.id')->toArray();
        $courseIds = $user->subscribedCourses()->pluck('courses.id')->toArray();

        $lectures = OnlineLecture::whereIn('group_id', $groupIds)
            ->orWhereIn('course_id', $courseIds)
            ->with(['teacher', 'course', 'group', 'level'])
            ->orderBy('start_date_time', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $lectures
        ]);
    }
}
