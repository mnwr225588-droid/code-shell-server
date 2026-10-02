<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseGroup;
use Illuminate\Http\Request;

class AdminGroupController extends Controller
{
    public function index($courseId)
    {
        $groups = CourseGroup::where('course_id', $courseId)
            ->with(['teacher'])
            ->withCount('students')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $groups
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'registration_deadline' => 'nullable|date',
            'status' => 'required|in:open_for_registration,waiting_for_students,ready_to_start,active,completed',
            'duration_days' => 'nullable|integer|min:0',
            'is_auto_create' => 'nullable|boolean',
            'teacher_id' => 'nullable',
            'teacher_name' => 'nullable|string',
            'registration_period_days' => 'nullable|integer|min:1',
        ]);

        $data = $request->only(['course_id', 'name', 'capacity', 'registration_deadline', 'status', 'duration_days', 'is_auto_create', 'teacher_id', 'registration_period_days']);

        // ⚡ الإنشاء التلقائي للمجموعة التالية مفعّل افتراضياً
        $data['is_auto_create'] = $request->boolean('is_auto_create', true);

        // مدة التسجيل الافتراضية 7 أيام
        $data['registration_period_days'] = $request->integer('registration_period_days', 7);

        if (empty($data['teacher_id']) || !\App\Models\Teacher::where('id', $data['teacher_id'])->exists()) {
            $data['teacher_id'] = null;
        }

        if (!empty($data['duration_days']) && $data['duration_days'] > 0) {
            $data['registration_deadline'] = now()->addDays($data['duration_days']);
        }

        // إذا كانت المجموعة في حالة انتظار، حدد موعد التفعيل التلقائي
        if ($data['status'] === 'waiting_for_students' && empty($data['activation_scheduled_at'])) {
            $data['activation_scheduled_at'] = now()->addDays($data['registration_period_days']);
        }

        $group = CourseGroup::create($data);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة والمجموعة بنجاح',
            'data' => $group->load('teacher')
        ]);
    }

    public function update(Request $request, $id)
    {
        $group = CourseGroup::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|integer|min:1',
            'registration_deadline' => 'nullable|date',
            'status' => 'sometimes|in:open_for_registration,waiting_for_students,ready_to_start,active,completed',
            'duration_days' => 'nullable|integer|min:0',
            'is_auto_create' => 'nullable|boolean',
        ]);
        
        $data = $request->all();
        if (isset($data['duration_days']) && $data['duration_days'] > 0) {
            $data['registration_deadline'] = now()->addDays($data['duration_days']);
        }

        $group->update($data);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث المجموعة بنجاح',
            'data' => $group
        ]);
    }

    public function activate($id)
    {
        $group = CourseGroup::findOrFail($id);
        
        \App\Services\CourseGroupService::activateAndSpawnNext($group);
        
        // Refresh to get the latest status
        $group->refresh();

        return response()->json([
            'status' => true,
            'message' => 'تم تفعيل المجموعة وفتح المحتوى للطلاب بنجاح',
            'data' => $group
        ]);
    }
}
