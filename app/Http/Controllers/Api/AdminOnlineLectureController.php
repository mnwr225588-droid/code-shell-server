<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineLecture;
use App\Services\ZoomService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminOnlineLectureController extends Controller
{
    protected $zoomService;

    public function __construct(ZoomService $zoomService)
    {
        $this->zoomService = $zoomService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'level_id' => 'required|exists:levels,id',
            'group_id' => 'required|exists:course_groups,id',
            'teacher_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i', // Format 24H from frontend or AM/PM converted
            'timezone' => 'required|string', // e.g., 'Africa/Cairo'
            'duration_minutes' => 'required|integer|min:1',
        ]);

        // Convert the input time (based on input timezone) to UTC
        $startDateTimeLocal = Carbon::parse($request->start_date . ' ' . $request->start_time, $request->timezone);
        $startDateTimeUTC = $startDateTimeLocal->copy()->setTimezone('UTC');

        // Create Zoom Meeting
        $zoomMeeting = $this->zoomService->createMeeting([
            'topic' => $request->title,
            'start_time' => $startDateTimeUTC->format('Y-m-d\TH:i:s\Z'),
            'duration' => $request->duration_minutes,
            'agenda' => $request->description,
        ]);

        if (!$zoomMeeting) {
            return response()->json([
                'status' => false,
                'message' => 'فشل في إنشاء اجتماع Zoom. يرجى التحقق من إعدادات الـ API.'
            ], 500);
        }

        $lecture = OnlineLecture::create([
            'course_id' => $request->course_id,
            'level_id' => $request->level_id,
            'group_id' => $request->group_id,
            'teacher_id' => $request->teacher_id,
            'title' => $request->title,
            'description' => $request->description,
            'start_date_time' => $startDateTimeUTC,
            'timezone' => $request->timezone,
            'duration_minutes' => $request->duration_minutes,
            'zoom_meeting_id' => $zoomMeeting['id'] ?? null,
            'zoom_join_url' => $zoomMeeting['join_url'] ?? null,
            'zoom_start_url' => $zoomMeeting['start_url'] ?? null,
            'status' => 'scheduled',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة المحاضرة الأونلاين وإنشاء اجتماع Zoom بنجاح',
            'data' => $lecture
        ]);
    }

    public function update(Request $request, $id)
    {
        $lecture = OnlineLecture::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:scheduled,live,ended,cancelled'
        ]);

        $lecture->update($request->only('status'));

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث المحاضرة بنجاح',
            'data' => $lecture
        ]);
    }
}
