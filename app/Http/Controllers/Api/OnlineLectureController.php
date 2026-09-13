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

        // 1. Verify user's group subscription (students only)
        $isTeacher = $userId == $lecture->teacher_id;
        
        if (!$isTeacher && !$isAdmin) {
            $subscription = \DB::table('course_subscriptions')
                ->where('user_id', $userId)
                ->where('course_id', $lecture->course_id)
                ->where('group_id', $lecture->group_id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بدخول هذه المحاضرة. أنت لست في المجموعة الصحيحة.'
                ], 403);
            }
        }

        // 2. Verify time (must be at most 10 minutes before start)
        $now = Carbon::now('UTC');
        $startTime = Carbon::parse($lecture->start_date_time, 'UTC');

        if (!$isAdmin && !$isTeacher && $now->lt($startTime->copy()->subMinutes(10))) {
            return response()->json([
                'status' => false,
                'message' => 'زر الانضمام غير متاح حالياً. يرجى الانتظار حتى 10 دقائق قبل المحاضرة.'
            ], 403);
        }

        // 3. Return appropriate URL based on user role
        if ($isAdmin || $isTeacher) {
            // المعلم أو الأدمن: يعود برابط البداية (Host) مع صلاحيات كاملة
            return response()->json([
                'status' => true,
                'message' => 'تم التحقق بنجاح',
                'data' => [
                    'role' => 'host',
                    'join_url' => $lecture->zoom_start_url,
                ]
            ]);
        } else {
            // الطلاب: يعود برابط المشاركة (Participant)
            $deepLink = 'zoomus://' . str_replace('https://', '', $lecture->zoom_join_url);
            return response()->json([
                'status' => true,
                'message' => 'تم التحقق بنجاح',
                'data' => [
                    'role' => 'student',
                    'join_url' => $deepLink,
                ]
            ]);
        }
    }
}
