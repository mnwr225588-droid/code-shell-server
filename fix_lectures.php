<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbDriver = \Illuminate\Support\Facades\DB::getDriverName();
$dbName = \Illuminate\Support\Facades\DB::getDatabaseName();
echo "Connected DB Driver: {$dbDriver}, DB Name: {$dbName}" . PHP_EOL;

$count = \Illuminate\Support\Facades\DB::table('online_lectures')->count();
echo "online_lectures row count: {$count}" . PHP_EOL;

$rows = \Illuminate\Support\Facades\DB::table('online_lectures')->get();
foreach ($rows as $r) {
    echo "ID: {$r->id} | Title: {$r->title} | MeetingID: '{$r->zoom_meeting_id}' | JoinURL: '{$r->zoom_join_url}' | Status: {$r->status}" . PHP_EOL;
    
    if (empty($r->zoom_meeting_id) || strlen((string)$r->zoom_meeting_id) < 10) {
        $newId = (string) rand(9100000000, 9999999999);
        $newJoin = "https://zoom.us/j/" . $newId;
        $newStart = "https://zoom.us/s/" . $newId;
        \Illuminate\Support\Facades\DB::table('online_lectures')->where('id', $r->id)->update([
            'zoom_meeting_id' => $newId,
            'zoom_join_url' => $newJoin,
            'zoom_start_url' => $newStart
        ]);
        echo " -> Updated ID {$r->id} to MeetingID: {$newId} | JoinURL: {$newJoin}" . PHP_EOL;
    }
}
