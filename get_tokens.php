<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokens = App\Models\User::whereNotNull('fcm_token')->where('fcm_token', 'not like', 'fake_%')->pluck('fcm_token')->toArray();
echo "Real tokens found:\n";
foreach ($tokens as $token) {
    echo "- " . substr($token, 0, 15) . "...\n";
}
