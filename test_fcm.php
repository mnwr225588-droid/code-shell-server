<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

$token = $argv[1] ?? null;

if (!$token) {
    echo "Usage: php test_fcm.php <FCM_TOKEN>\n";
    exit(1);
}

echo "Testing FCM delivery to token: " . substr($token, 0, 15) . "...\n";

$messaging = app('firebase.messaging');

echo "\n--- TEST A: Sending simple notification (like Firebase Console) ---\n";
$notification = Notification::create('Test Title (Simple)', 'This is a simple test body');
$message = CloudMessage::new()
    ->withToken($token)
    ->withNotification($notification);

try {
    $result = $messaging->send($message);
    echo "Test A Sent Successfully. Result: " . json_encode($result) . "\n";
} catch (\Exception $e) {
    echo "Test A Failed: " . $e->getMessage() . "\n";
}

echo "\n--- TEST B: Sending full payload (like PushNotificationService) ---\n";
$title = 'Test Title (Full Payload)';
$body = 'This is a full payload test body';
$data = ['type' => 'admin', 'notification_id' => '123', 'course_id' => '456'];

$notification = Notification::create($title, $body);
$message = CloudMessage::new()
    ->withToken($token)
    ->withNotification($notification)
    ->withData($data);

$androidConfig = [
    'priority' => 'high',
    'notification' => [
        'channel_id' => 'codeshell_notifications',
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        'title' => $title,
        'body'  => $body,
        'sound' => 'default',
        'default_sound' => true,
        'notification_priority' => 'PRIORITY_HIGH',
    ],
];
$message = $message->withAndroidConfig($androidConfig);

try {
    $result = $messaging->send($message);
    echo "Test B Sent Successfully. Result: " . json_encode($result) . "\n";
} catch (\Exception $e) {
    echo "Test B Failed: " . $e->getMessage() . "\n";
}
