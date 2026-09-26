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
            ['title' => 'منهج البرمجة ثانية بكالوريا'],
            [
                'category_id' => $category->id,
                'description' => 'منهج البرمجة الشامل لطلبة ثانية بكالوريا بشرح مباشر أونلاين (ليست كورسات مسجلة مسبقاً)، مع متابعة حية ومشاريع تطبيقية.',
                'price' => 1.00,
                'prices' => ['EGP' => 1, 'USD' => 1],
                'is_active' => true,
                'is_coming_soon' => false,
                'duration' => 'ترم كامل / شهر',
                'difficulty' => 'متوسط',
            ]
        );
    }
}
