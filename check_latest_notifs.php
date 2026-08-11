<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LATEST 3 NOTIFICATION SENDS ===\n";
foreach(App\Models\NotificationSend::latest()->take(3)->get() as $n) {
    echo $n->toJson(JSON_PRETTY_PRINT) . "\n";
}

echo "=== LATEST 3 NOTIFICATIONS ===\n";
foreach(App\Models\Notification::latest()->take(3)->get() as $n) {
    echo $n->toJson(JSON_PRETTY_PRINT) . "\n";
}
