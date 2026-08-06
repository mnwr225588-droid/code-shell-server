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

            // 1) حفظ في القاعدة — يظهر في شاشة الإشعارات داخل التطبيق
            \App\Models\Notification::create([
                'user_id'   => $user->id,
                'title'     => $title,
                'body'      => $body,
                'type'      => $type,
                'course_id' => $courseId,
                'data'      => array_merge(
                    $data,
                    $imageUrl !== null && !array_key_exists('image_url', $data)
                        ? ['image_url' => $imageUrl]
                        : [],
                ),
            ]);
            $saved++;

            // 2) الإرسال الفوري عبر FCM
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
                        ['type' => $type],
                    ));

                // صورة كبيرة في شريط الإشعارات (Android 7+)
                if ($imageUrl !== null) {
                    $message = $message->withAndroidConfig([
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                            'image' => $imageUrl,
                        ],
                    ]);
                }

                app('firebase.messaging')->send($message);
                $fcmSent++;
            } catch (\Throwable $e) {
                Log::error('FCM Error for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        return [
            'saved'    => $saved,
            'fcm_sent' => $fcmSent,
            'no_token' => $noToken,
        ];
    }
}
