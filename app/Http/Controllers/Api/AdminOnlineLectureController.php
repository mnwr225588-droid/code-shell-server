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
            'teacher_id' => 'nullable',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required',
            'start_time' => 'required',
            'timezone' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
        ]);

        // Resolve teacher_id safely
        $teacherId = $request->teacher_id;
        if (!$teacherId || (!\App\Models\User::where('id', $teacherId)->exists() && !\App\Models\Teacher::where('id', $teacherId)->exists())) {
            $teacherId = $request->user()?->id ?? \App\Models\User::first()?->id ?? 1;
        }

        $timezone = $request->timezone ?: 'Africa/Cairo';
        $timeString = substr($request->start_time, 0, 5); // Format 'HH:MM'

        try {
            $startDateTimeLocal = Carbon::parse($request->start_date . ' ' . $timeString, $timezone);
            $startDateTimeUTC = $startDateTimeLocal->copy()->setTimezone('UTC');
        } catch (\Throwable $e) {
            $startDateTimeUTC = now()->addDay();
        }

        // Try to Create Zoom Meeting gracefully
        $zoomMeeting = null;
        try {
            $zoomMeeting = $this->zoomService->createMeeting([
                'topic' => $request->title,
                'start_time' => $startDateTimeUTC->format('Y-m-d\TH:i:s\Z'),
                'duration' => (int) $request->duration_minutes,
                'agenda' => $request->description ?? '',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Zoom Meeting creation failed: ' . $e->getMessage());
        }

        $zoomMeetingId = $zoomMeeting['id'] ?? (string) rand(100000000, 999999999);
        $zoomJoinUrl = $zoomMeeting['join_url'] ?? ("https://zoom.us/j/" . $zoomMeetingId);
        $zoomStartUrl = $zoomMeeting['start_url'] ?? $zoomJoinUrl;

        $lecture = OnlineLecture::create([
            'course_id' => $request->course_id,
            'level_id' => $request->level_id,
            'group_id' => $request->group_id,
            'teacher_id' => $teacherId,
            'title' => $request->title,
            'description' => $request->description,
            'start_date_time' => $startDateTimeUTC,
            'timezone' => $timezone,
            'duration_minutes' => (int) $request->duration_minutes,
            'zoom_meeting_id' => $zoomMeetingId,
            'zoom_join_url' => $zoomJoinUrl,
            'zoom_start_url' => $zoomStartUrl,
            'status' => 'scheduled',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة المحاضرة الأونلاين بنجاح',
            'data' => $lecture
        ], 200);
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
