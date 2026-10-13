<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = 0;
DB::listen(function($query) use (&$count) {
    $count++;
    echo "[QUERY $count] " . $query->sql . "\n";
});

$user = App\Models\User::first();
auth('sanctum')->setUser($user);

$request = new Illuminate\Http\Request();
$request->setUserResolver(fn() => $user);

$controller = new App\Http\Controllers\Api\CourseController();
$res = $controller->index($request);
echo "TOTAL QUERIES: $count\n";
