<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إضافة تصنيف "المسارات التعليمية" إذا لم يكن موجوداً
        DB::table('categories')->insertOrIgnore([
            'id' => 4,
            'name' => 'المسارات التعليمية',
            'icon' => '🎓',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // إضافة كورس "منهج البرمجة ثانية بكالوريا"
        DB::table('courses')->insertOrIgnore([
            'id' => 10,
            'category_id' => 4,
            'title' => 'منهج البرمجة ثانية بكالوريا',
            'description' => 'كورس متخصص في شرح منهج البرمجة لثانوية عامة (ثانية بكالوريا) - محاضرات أونلاين مباشرة مع مدرسين متخصصين. يغطي جميع مفاهيم البرمجة المقررة في المنهج الوزاري مع شرح مفصل وحل أسئلة امتحانية.',
            'thumbnail' => null,
            'is_free' => false,
            'price' => 1.00,
            'prices' => json_encode([
                'EG' => ['price' => 1.00, 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م'],
                'SA' => ['price' => 1.00, 'currency_code' => 'SAR', 'currency_symbol' => 'ر.س'],
                'AE' => ['price' => 1.00, 'currency_code' => 'AED', 'currency_symbol' => 'د.إ'],
                'JO' => ['price' => 1.00, 'currency_code' => 'JOD', 'currency_symbol' => 'د.أ'],
                'PS' => ['price' => 1.00, 'currency_code' => 'ILS', 'currency_symbol' => '₪'],
            ]),
            'is_coming_soon' => false,
            'is_active' => true,
            'sort_order' => 10,
            'duration' => '90 يوم',
            'difficulty' => 'متوسط',
            'features' => json_encode([
                'محاضرات أونلاين مباشرة',
                'تغطية كاملة لمنهج الوزارة',
                'حل الأسئلة والتدريبات الامتحانية',
                'شهادة إتمام معتمدة',
                'دعم ومتابعة مستمرة',
            ]),
            'what_will_learn' => json_encode([
                'شرح مفهوم البرمجة والخوارزميات الأساسية',
                'دراسة اللغات البرمجية المقررة في المنهج',
                'حل الأسئلة والتدريبات الامتحانية',
                'الاستعداد للامتحانات النهائية بشكل احترافي',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // حذف الكورس
        DB::table('courses')->where('id', 10)->delete();
        
        // حذف التصنيف إذا لم يكن يستخدم
        $categoryUsage = DB::table('courses')->where('category_id', 4)->count();
        if ($categoryUsage === 0) {
            DB::table('categories')->where('id', 4)->delete();
        }
    }
};
