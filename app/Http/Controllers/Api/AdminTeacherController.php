<?php

namespace App\Http\Controllers\Api;

/// AdminTeacherController
/// يتيح للأدمن: إضافة مدرسين، عرضهم، ومراجعة طلبات تأجيل المحاضرات.

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\LecturePostponementRequest;
use App\Models\OnlineLecture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminTeacherController extends Controller
{
    /**
     * عرض جميع المدرسين.
     */
    public function index()
    {
        $teachers = Teacher::orderBy('id', 'desc')->get(['id', 'name', 'email', 'created_at']);

        return response()->json([
            'status' => true,
            'data' => $teachers,
        ]);
    }

    /**
     * إضافة مدرس جديد.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email',
            'password' => 'required|string|min:6',
        ]);

        $teacher = Teacher::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة المدرس بنجاح',
            'data' => $teacher,
        ]);
    }

    /**
     * حذف مدرس.
     */
    public function destroy($id)
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف المدرس بنجاح',
        ]);
    }

    /**
     * جلب طلبات التأجيل (الكل أو المعلقة فقط).
     */
    public function postponementRequests(Request $request)
    {
        $query = LecturePostponementRequest::with([
            'teacher:id,name',
            'onlineLecture:id,title,start_date_time,group_id',
            'onlineLecture.group:id,name',
        ]);

        if ($request->query('status') === 'pending') {
            $query->where('status', 'pending');
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $requests,
        ]);
    }

    /**
     * الموافقة على طلب تأجيل أو رفضه.
     */
    public function handlePostponement(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        $postponement = LecturePostponementRequest::with(['onlineLecture.group.students', 'teacher'])->findOrFail($id);

        if ($postponement->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'تم معالجة هذا الطلب مسبقاً',
            ], 422);
        }

        if ($request->action === 'approve') {
            $postponement->status = 'approved';
            $postponement->save();

            // تحديث موعد المحاضرة
            $lecture = $postponement->onlineLecture;
            $newDateTime = $postponement->new_date->format('Y-m-d') . ' ' . $postponement->new_time;
            $lecture->start_date_time = $newDateTime;
            $lecture->save();

            // إشعار طلاب المجموعة بالتأجيل
            try {
                $students = $lecture->group->students ?? collect();
                foreach ($students as $student) {
                    if ($student->fcm_token) {
                        $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $student->fcm_token)
                            ->withNotification(\Kreait\Firebase\Messaging\Notification::create(
                                'تم تأجيل المحاضرة 📅',
                                "تم تأجيل محاضرة '{$lecture->title}' إلى {$postponement->new_date->format('Y-m-d')} الساعة {$postponement->new_time}.\nالسبب: {$postponement->reason}"
                            ))
                            ->withData([
                                'type' => 'lecture_postponed',
                                'lecture_id' => (string) $lecture->id,
                            ]);
                        app('firebase.messaging')->send($message);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('FCM postponement approve notification error: ' . $e->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'تمت الموافقة على طلب التأجيل وتم إشعار الطلاب.',
            ]);
        } else {
            $postponement->status = 'rejected';
            $postponement->save();

            return response()->json([
                'status' => true,
                'message' => 'تم رفض طلب التأجيل.',
            ]);
        }
    }
}
