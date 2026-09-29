<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$courses = \App\Models\Course::select('id', 'title', 'category_id', 'created_at')->get();
echo "Total courses in DB: " . $courses->count() . "\n\n";
foreach ($courses as $c) {
    echo "ID: {$c->id} | Title: {$c->title} | Category: {$c->category_id}\n";
}
