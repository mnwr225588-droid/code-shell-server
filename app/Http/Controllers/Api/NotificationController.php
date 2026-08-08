<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * إشعارات المستخدم الحالي (الأحدث أولاً).
     * يقرأها تطبيق الطالب من شاشة الإشعارات.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function (Notification $n) {
                return [
                    'id'         => $n->id,
                    'title'      => $n->title,
                    'body'       => $n->body,
                    'type'       => $n->type,
                    'course_id'  => $n->course_id,
                    'image_url'  => $n->image_url,
                    'data'       => $n->data,
                    'is_read'    => $n->is_read,
                    'read_at'    => $n->read_at ? $n->read_at->toIso8601String() : null,
                    'created_at' => $n->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $notifications,
        ]);
    }

    /**
     * تعليم إشعار معين كمقروء.
     */
    public function markRead(Request $request, $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تعليم الإشعار كمقروء بنجاح',
        ]);
    }

    /**
     * تعليم جميع الإشعارات كمقروءة.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تعليم جميع الإشعارات كمقروءة',
        ]);
    }

    /**
     * إرسال إشعار من لوحة الأدمن (يُستدعى فقط ضمن مجموعة مسارات admin):
     * - target=all            : جميع المستخدمين (خلفية)
     * - target=course         : المحجوزون/المشتركون في كورس محدد (خلفية)
     * - target=not_subscribed : المستخدمون غير المشتركين وغير المحجوزين (خلفية)
     * - target=email          : مستخدم واحد عبر البريد الإلكتروني (فوري)
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'body'      => ['required', 'string'],
            'target'    => ['required', 'in:all,course,not_subscribed,email'],
            'course_id' => ['required_if:target,course', 'integer', 'exists:courses,id'],
            'email'     => ['required_if:target,email', 'email', 'exists:users,email'],
            'image'     => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'title.required'     => 'عنوان الإشعار مطلوب',
            'title.max'          => 'عنوان الإشعار يجب ألا يتجاوز 255 حرفاً',
            'body.required'      => 'نص الإشعار مطلوب',
            'target.required'    => 'اختر الفئة المستهدفة',
            'target.in'          => 'قيمة الاستهداف غير صالحة',
            'course_id.required_if' => 'اختر الكورس الذي سيُرسل إليه الإشعار',
            'course_id.exists'      => 'الكورس المختار غير موجود',
            'email.required_if'     => 'أدخل البريد الإلكتروني للطالب',
            'email.email'           => 'البريد الإلكتروني غير صالح',
            'email.exists'          => 'لا يوجد مستخدم مسجل بهذا البريد الإلكتروني',
            'image.image'           => 'الملف المرفق يجب أن يكون صورة',
            'image.mimes'           => 'الصورة يجب أن تكون بصيغة jpeg أو png أو jpg أو webp',
            'image.max'             => 'حجم الصورة يجب ألا يتجاوز 5 ميجابايت',
        ]);

        // الصورة اختيارية بالكامل:
        $imageUrl = null;
        $imageStoragePath = null;
        if ($request->hasFile('image')) {
            $imageStoragePath = $request->file('image')->store('notifications/images', 'public');

            $host = $request->getHost();
            $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
            $scheme = $isLocal ? 'http' : 'https';
            $imageUrl = $scheme . '://' . $host . '/storage/' . $imageStoragePath;
        }

        switch ($request->target) {
            case 'all':
                // كل المستخدمين الطلاب فقط — حسابات الأدمن لا تستقبل إشعارات الطلاب.
                $users = User::where('is_admin', false)->get();
                break;

            case 'course':
                $ids = DB::table('course_reservations')
                    ->where('course_id', $request->course_id)
                    ->pluck('user_id')
                    ->merge(
                        DB::table('course_subscriptions')
                            ->where('course_id', $request->course_id)
                            ->pluck('user_id')
                    )
                    ->unique()
                    ->values();
                $users = User::whereIn('id', $ids)->get();
                break;

            case 'not_subscribed':
                $involvedIds = DB::table('course_subscriptions')->pluck('user_id')
                    ->merge(DB::table('course_reservations')->pluck('user_id'))
                    ->unique();
                $users = User::whereNotIn('id', $involvedIds)->get();
                break;

            case 'email':
            default:
                $users = User::where('email', $request->email)->get();
                break;
        }

        if ($users->isEmpty()) {
            return response()->json([
                'status'     => false,
                'message'    => 'لا يوجد مستخدمون ينطبق عليهم هذا الخيار حالياً',
                'users_count' => 0,
                'fcm_sent'   => 0,
            ], 422);
        }

        // حفظ سجل الإرسال أولاً لتحديثه بعد الإرسال
        $notificationSend = NotificationSend::create([
            'title'       => $request->title,
            'body'        => $request->body,
            'image_url'   => $imageStoragePath,
            'target_type' => $request->target,
            'course_id'   => $request->filled('course_id') ? $request->course_id : null,
            'email'       => $request->filled('email') ? $request->email : null,
            'users_count' => $users->count(),
            'fcm_sent'    => 0,
            'no_token'    => 0,
            'sent_by'     => $request->user()?->id,
            'sent_at'     => now(),
        ]);

        // إذا كان الاستهداف لشخص واحد (مثل البريد)، يُنفّذ الإرسال بشكل synchronous
        if ($users->count() === 1) {
            $result = PushNotificationService::sendToUsers(
                users: $users,
                title: $request->title,
                body: $request->body,
                data: array_merge(
                    ['type' => 'admin'],
                    $imageStoragePath !== null ? ['image_url' => $imageStoragePath] : [],
                ),
                imageUrl: $imageUrl,
                courseId: $request->filled('course_id') ? $request->course_id : null,
                type: 'general',
            );

            // تحديث سجل الإرسال بالنتائج
            $notificationSend->update([
                'users_count' => $result['saved'],
                'fcm_sent'    => $result['fcm_sent'],
                'no_token'    => $result['no_token'],
            ]);

            return response()->json([
                'status'      => true,
                'message'     => 'تم إرسال الإشعار بنجاح.',
                'users_count' => $result['saved'],
                'fcm_sent'    => $result['fcm_sent'],
                'no_token'    => $result['no_token'],
            ]);
        }

        // إذا كان الاستهداف جماعياً، يتم جدولته في الـ Queue
        \App\Jobs\SendPushNotificationJob::dispatch(
            $users->pluck('id')->toArray(),
            $request->title,
            $request->body,
            array_merge(
                ['type' => 'admin'],
                $imageStoragePath !== null ? ['image_url' => $imageStoragePath] : [],
            ),
            $imageUrl,
            $request->filled('course_id') ? $request->course_id : null,
            'general',
            $notificationSend->id
        );

        return response()->json([
            'status'      => true,
            'message'     => 'تم بدء إرسال الإشعار في الخلفية.',
            'users_count' => $users->count(),
            'fcm_sent'    => 0,
            'no_token'    => 0,
        ]);
    }

    /**
     * سجل الإرسالات السابقة (لتطبيق الأدمن) — الأحدث أولاً.
     */
    public function history(Request $request): JsonResponse
    {
        $sends = NotificationSend::orderBy('sent_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function (NotificationSend $s) {
                return [
                    'id'          => $s->id,
                    'title'       => $s->title,
                    'body'        => $s->body,
                    'image_url'   => $s->image_url,
                    'target_type' => $s->target_type,
                    'course_id'   => $s->course_id,
                    'email'       => $s->email,
                    'users_count' => (int) $s->users_count,
                    'fcm_sent'    => (int) $s->fcm_sent,
                    'no_token'    => (int) $s->no_token,
                    'sent_at'     => $s->sent_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $sends,
        ]);
    }
}
