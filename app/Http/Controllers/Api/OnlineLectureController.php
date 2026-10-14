<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineLecture;
use App\Models\CourseSubscription;
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
            $subscription = CourseSubscription::where('user_id', $userId)
                ->where('course_id', $lecture->course_id)
                ->where(function ($query) {
                    $query->whereNull('subscription_status')
                        ->orWhere('subscription_status', CourseSubscription::STATUS_ACTIVE);
                })
                ->with('plan')
                ->first();

            if (!$subscription || !$subscription->isAccessGranted()) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بدخول هذه المحاضرة. يجب الاشتراك في الكورس أولاً.'
                ], 403);
            }

            if ($lecture->plan_id && (int) $lecture->plan_id !== (int) $subscription->plan_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكنك دخول محاضرة تابعة لباقة لا تملك اشتراكاً فيها.',
                ], 403);
            }

            // Sync group_id if missing or update to match lecture group
            if (empty($subscription->group_id) || $subscription->group_id != $lecture->group_id) {
                $subscription->group_id = $lecture->group_id;
                $subscription->save();
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

        $subscriptions = CourseSubscription::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('subscription_status')
                    ->orWhere('subscription_status', CourseSubscription::STATUS_ACTIVE);
            })
            ->with('plan')
            ->get();

        $subscriptions = $subscriptions->filter(
            fn (CourseSubscription $subscription) => $subscription->isAccessGranted()
        );

        if ($subscriptions->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => [],
            ]);
        }

        // جلب معرفات المجموعات التي ينتمي لها الطالب
        $groupIds = $user->groups()->pluck('course_groups.id')->toArray();

        // جلب المحاضرات الأونلاين فقط للمجموعات التي ينتمي لها الطالب
        // إزالة orWhereIn('course_id') لضمان أن كل مجموعة ترى محاضراتها الخاصة فقط
        $lecturesQuery = OnlineLecture::whereIn('group_id', $groupIds)
            ->with(['teacher', 'course', 'group', 'level'])
            ->where(function ($query) use ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $query->orWhere(function ($courseQuery) use ($subscription) {
                        $courseQuery->where('course_id', $subscription->course_id);
                        if ($subscription->plan_id) {
                            $courseQuery->where(function ($planQuery) use ($subscription) {
                                $planQuery->where('plan_id', $subscription->plan_id)
                                    ->orWhereNull('plan_id');
                            });
                        } else {
                            $courseQuery->whereNull('plan_id');
                        }
                    });
                }
            });

        $lectures = $lecturesQuery->orderBy('start_date_time', 'asc')->get();

        return response()->json([
            'status' => true,
            'data' => $lectures
        ]);
    }
}
