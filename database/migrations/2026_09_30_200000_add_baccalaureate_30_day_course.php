<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Course;
use App\Models\Category;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة كورس البكالوريا الجديد مدة اشتراكه 30 يوم
        // استخدام category_id مباشرة للبكالوريا (نفترض أنها 2 بناءً على الكود الموجود)
        $baccalaureateCategoryId = 2;
        
        // التحقق من عدم وجود الكورس مسبقاً
        $existingCourse = Course::where('title', 'منهج البرمجة - اشتراك شهري')->first();
        
        if (!$existingCourse) {
            Course::create([
                'category_id' => $baccalaureateCategoryId,
                'title' => 'منهج البرمجة - اشتراك شهري',
                'description' => 'اشتراك شهري في منهج البرمجة للبكالوريا - مدة الاشتراك 30 يوم',
                'thumbnail' => null,
                'is_free' => false,
                'price' => 500, // السعر يمكن تعديله حسب الحاجة
                'prices' => json_encode(['EGP' => 500]),
                'is_coming_soon' => false,
                'is_active' => true,
                'subscription_duration_days' => 30, // مدة الاشتراك 30 يوم
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف الكورس المضاف
        Course::where('title', 'منهج البرمجة - اشتراك شهري')->delete();
    }
};
