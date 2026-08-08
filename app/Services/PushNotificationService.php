<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * خدمة موحّدة لإرسال الإشعارات:
 * 1) حفظ الإشعار في جدول notifications لكل مستخدم (يظهر في شاشة إشعارات التطبيق).
 * 2) إرسال إشعار فوري عبر Firebase FCM (يعمل حتى لو كان التطبيق مغلقاً تماماً).
 */
class PushNotificationService
{
    /**
     * @param  iterable<\App\Models\User>  $users
     * @param  array<string, mixed>  $data
     * @return array{saved: int, fcm_sent: int, no_token: int}
     */
    public static function sendToUsers(
        iterable $users,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null,
        ?int $courseId = null,
        string $type = 'general',
    ): array {
        $saved = 0;
        $fcmSent = 0;
        $noToken = 0;

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            // 1) De-duplication Check: Prevent duplicate notifications for same user + course + type (specifically course_available)
            if ($type === 'course_available' && $courseId !== null) {
                $exists = \App\Models\Notification::where('user_id', $user->id)
                    ->where('type', 'course_available')
                    ->where('course_id', $courseId)
                    ->exists();
                if ($exists) {
                    Log::info("Duplicate notification prevented for user #{$user->id} on course #{$courseId} (type: course_available)");
                    continue;
                }
            }

            // 2) حفظ في القاعدة — يظهر في شاشة الإشعارات داخل التطبيق
            $notif = \App\Models\Notification::create([
                'user_id'   => $user->id,
                'title'     => $title,
                'body'      => $body,
                'type'      => $type,
                'course_id' => $courseId,
                'image_url' => $imageUrl,
                'data'      => array_merge(
                    $data,
                    $imageUrl !== null && !array_key_exists('image_url', $data)
                        ? ['image_url' => $imageUrl]
                        : [],
                ),
            ]);
            $saved++;

            // 3) الإرسال الفوري عبر FCM
            if (empty($user->fcm_token)) {
                $noToken++;
                continue;
            }

            try {
                $notification = \Kreait\Firebase\Messaging\Notification::create($title, $body);

                $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                    ->withToken($user->fcm_token)
                    ->withNotification($notification)
                    ->withData(array_merge(
                        $data,
                        $imageUrl !== null && !array_key_exists('image_url', $data)
                            ? ['image_url' => $imageUrl]
                            : [],
                        [
                            'type' => $type,
                            'notification_id' => (string)$notif->id,
                        ],
                    ));

                // صورة كبيرة في شريط الإشعارات (Android 7+) وتكوين القناة للأندرويد
                $androidConfig = [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'codeshell_notifications',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'title' => $title,
                        'body'  => $body,
                    ],
                ];

                if ($imageUrl !== null) {
                    $androidConfig['notification']['image'] = $imageUrl;
                }

                $message = $message->withAndroidConfig($androidConfig);

                app('firebase.messaging')->send($message);
                $fcmSent++;
            } catch (\Throwable $e) {
                // تسجيل الخطأ الفعلي بالتفصيل في الـ logs الفنية للسيرفر
                Log::error('FCM Delivery failure for user #' . $user->id . ' - Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

                // التحقق مما إذا كان الـ Token غير صالح لتنظيفه من قاعدة البيانات
                $isInvalidToken = false;
                if ($e instanceof \Kreait\Firebase\Exception\Messaging\NotFound) {
                    $isInvalidToken = true;
                } else {
                    $msg = strtolower($e->getMessage());
                    if (str_contains($msg, 'unregistered') || 
                        str_contains($msg, 'notregistered') || 
                        str_contains($msg, 'invalid') || 
                        str_contains($msg, 'registration') ||
                        str_contains($msg, 'token')) {
                        $isInvalidToken = true;
                    }
                }

                if ($isInvalidToken) {
                    $user->fcm_token = null;
                    $user->save();
                    Log::info("Cleaned up invalid FCM token for user #{$user->id}");
                }
            }
        }

        return [
            'saved'    => $saved,
            'fcm_sent' => $fcmSent,
            'no_token' => $noToken,
        ];
    }
}
