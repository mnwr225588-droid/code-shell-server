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
        ]);

        $data = $request->all();
        if (empty($data['teacher_id']) || !\App\Models\Teacher::where('id', $data['teacher_id'])->exists()) {
            $data['teacher_id'] = null;
        }

        if (!empty($data['duration_days']) && $data['duration_days'] > 0) {
            $data['registration_deadline'] = now()->addDays($data['duration_days']);
        }

        if (!empty($data['teacher_id']) && empty($data['teacher_name'])) {
            $teacher = \App\Models\Teacher::find($data['teacher_id']);
            if ($teacher) {
                $data['teacher_name'] = $teacher->name;
            }
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
    public function destroy($id)
    {
        $group = CourseGroup::findOrFail($id);
        if ($group->students_count > 0) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف مجموعة تحتوي على طلاب.',
            ], 422);
        }
        $group->delete();
        return response()->json([
            'status' => true,
            'message' => 'تم حذف المجموعة بنجاح',
        ]);
    }
}
