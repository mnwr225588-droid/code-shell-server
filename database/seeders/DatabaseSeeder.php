<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $category = Category::firstOrCreate(
            ['name' => 'المسارات والصفوف التعليمية']
        );

        Course::updateOrCreate(
            ['id' => 10],
            [
                'title' => 'منهج البرمجة ثانية بكالوريا',
                'category_id' => $category->id,
                'description' => 'منهج البرمجة الشامل لطلبة ثانية بكالوريا بشرح مباشر أونلاين (ليست كورسات مسجلة مسبقاً)، مع متابعة حية ومشاريع تطبيقية.',
                'price' => 100.00,
                'prices' => ['EGP' => 300, 'USD' => 100, 'SAR' => 40],
                'is_free' => false,
                'is_active' => true,
                'is_coming_soon' => false,
                'duration' => '90 يوم',
                'difficulty' => 'متوسط',
            ]
        );

        $this->call(CoursePlansSeeder::class);
    }
}
