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
        $lecture = OnlineLecture::findOrFail($id);

        // 1. Verify user's group subscription
        $subscription = \DB::table('course_subscriptions')
            ->where('user_id', $userId)
            ->where('course_id', $lecture->course_id)
            ->where('group_id', $lecture->group_id)
            ->first();

        $isAdmin = auth('sanctum')->user()?->isAdmin() ?? false;

        if (!$subscription && !$isAdmin) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بدخول هذه المحاضرة. أنت لست في المجموعة الصحيحة.'
            ], 403);
        }

        // 2. Verify time (must be at most 10 minutes before start)
        $now = Carbon::now('UTC');
        $startTime = Carbon::parse($lecture->start_date_time, 'UTC');

        if (!$isAdmin && $now->lt($startTime->copy()->subMinutes(10))) {
            return response()->json([
                'status' => false,
                'message' => 'زر الانضمام غير متاح حالياً. يرجى الانتظار حتى 10 دقائق قبل المحاضرة.'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم التحقق بنجاح',
            'data' => [
                'zoom_join_url' => $lecture->zoom_join_url,
                'deep_link' => 'zoomus://' . str_replace('https://', '', $lecture->zoom_join_url),
            ]
        ]);
    }
}
