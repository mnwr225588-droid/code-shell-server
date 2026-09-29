<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Course;
use App\Models\Category;

// 1. Get or create Baccalaureate category
$cat = Category::firstOrCreate(
    ['name' => 'المناهج التعليمية'],
    ['slug' => 'baccalaureate', 'icon' => '📚']
);
$cat->update(['slug' => 'baccalaureate']);

// 2. Delete duplicate baccalaureate courses except ID 10
$bacCourses = Course::where('title', 'like', '%بكالوريا%')->get();
foreach ($bacCourses as $c) {
    if ($c->id != 10) {
        echo "Deleting duplicate baccalaureate course ID: {$c->id}\n";
        $c->delete();
    }
}

// 3. Ensure course ID 10 exists and is configured properly
$course10 = Course::find(10);
if (!$course10) {
    echo "Creating Baccalaureate course with ID 10...\n";
    $course10 = new Course();
    $course10->id = 10;
}

$course10->category_id = $cat->id;
$course10->title = 'منهج البرمجة ثانية بكالوريا';
$course10->description = 'كورس متخصص في شرح منهج البرمجة لثانوية عامة (ثانية بكالوريا) - محاضرات أونلاين مباشرة مع مدرسين متخصصين. يغطي جميع مفاهيم البرمجة المقررة في المنهج الوزاري مع شرح مفصل وحل أسئلة امتحانية.';
$course10->price = 100.00;
$course10->prices = ['EGP' => 300, 'USD' => 100, 'SAR' => 40];
$course10->is_free = false;
$course10->is_active = true;
$course10->is_coming_soon = false;
$course10->duration = '90 يوم';
$course10->difficulty = 'متوسط';
$course10->save();

echo "SUCCESS! Baccalaureate course configured cleanly with ID 10.\n";
echo "Total courses remaining in DB: " . Course::count() . "\n";
