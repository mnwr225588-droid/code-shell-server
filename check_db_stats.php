<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Recent NotificationSends ---\n";
$sends = App\Models\NotificationSend::latest()->take(5)->get(['id', 'title', 'users_count', 'fcm_sent', 'no_token', 'created_at']);
foreach($sends as $s) {
    echo "ID: {$s->id} | Title: {$s->title} | Users: {$s->users_count} | FCM Sent: {$s->fcm_sent} | No Token: {$s->no_token} | Date: {$s->created_at}\n";
}

echo "\n--- Recent Notifications in DB ---\n";
$notifs = App\Models\Notification::latest()->take(5)->get(['id', 'user_id', 'title', 'created_at']);
foreach($notifs as $n) {
    echo "ID: {$n->id} | User: {$n->user_id} | Title: {$n->title} | Date: {$n->created_at}\n";
}

echo "\n--- User FCM Tokens ---\n";
$users = App\Models\User::whereNotNull('fcm_token')->get(['id', 'email', 'fcm_token']);
echo "Total users with tokens: " . $users->count() . "\n";
foreach($users as $u) {
    echo "User ID: {$u->id} | Email: {$u->email} | Token (starts with): " . substr($u->fcm_token, 0, 20) . "\n";
}

echo "\n--- Recent Log Errors ---\n";
if (file_exists('storage/logs/laravel.log')) {
    $logs = file('storage/logs/laravel.log');
    $errors = array_filter($logs, function($line) {
        return str_contains(strtolower($line), 'error') || str_contains(strtolower($line), 'exception');
    });
    echo implode("", array_slice($errors, -20));
} else {
    echo "No log file found.\n";
}
