<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CoursePlan;
use Illuminate\Database\Seeder;

class CoursePlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // الباقات لكورس منهج البرمجة ثانية بكالوريا (ID: 10)
        $baccalaureateCourse = Course::where('id', 10)->first();

        if ($baccalaureateCourse) {
            // باقة اشتراك شهري
            CoursePlan::updateOrCreate(
                [
                    'course_id' => $baccalaureateCourse->id,
                    'slug' => 'monthly',
                ],
                [
                    'name' => 'اشتراك شهري (30 يوماً)',
                    'name_en' => 'Monthly Subscription (30 days)',
                    'description' => 'اشتراك شهري مرن ومناسب للمتابعة الشهرية. يبدأ احتساب مدة الاشتراك من تاريخ نزول أول محاضرة أونلاين.',
                    'price' => 1.00,
                    'currency' => 'EGP',
                    'prices' => json_encode([
                        'EG' => ['price' => 1.00, 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م'],
                        'SA' => ['price' => 1.00, 'currency_code' => 'SAR', 'currency_symbol' => 'ر.س'],
                        'AE' => ['price' => 1.00, 'currency_code' => 'AED', 'currency_symbol' => 'د.إ'],
                        'JO' => ['price' => 1.00, 'currency_code' => 'JOD', 'currency_symbol' => 'د.أ'],
                        'PS' => ['price' => 1.00, 'currency_code' => 'ILS', 'currency_symbol' => '₪'],
                    ]),
                    'duration_days' => 30,
                    'duration_type' => 'days',
                    'is_active' => true,
                    'sort_order' => 1,
                    'features' => [
                        'متابعة حية وشرح مباشر أونلاين',
                        'مدة اشتراك 30 يوماً من أول محاضرة',
                        'يمكن تجديد الاشتراك في أي وقت',
                        'دعم ومتابعة مستمرة',
                    ],
                ]
            );

            // باقة اشتراك الترم الأول (ثلاثة أشهر)
            CoursePlan::updateOrCreate(
                [
                    'course_id' => $baccalaureateCourse->id,
                    'slug' => 'term_3months',
                ],
                [
                    'name' => 'اشتراك ثلاث شهور (الترم الأول)',
                    'name_en' => '3-Month Subscription (First Term)',
                    'description' => 'اشتراك متكامل يغطي الترم الأول. يبدأ احتساب مدة الاشتراك (90 يوماً) من تاريخ نزول أول محاضرة أونلاين.',
                    'price' => 1.00,
                    'currency' => 'EGP',
                    'prices' => json_encode([
                        'EG' => ['price' => 1.00, 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م'],
                        'SA' => ['price' => 1.00, 'currency_code' => 'SAR', 'currency_symbol' => 'ر.س'],
                        'AE' => ['price' => 1.00, 'currency_code' => 'AED', 'currency_symbol' => 'د.إ'],
                        'JO' => ['price' => 1.00, 'currency_code' => 'JOD', 'currency_symbol' => 'د.أ'],
                        'PS' => ['price' => 1.00, 'currency_code' => 'ILS', 'currency_symbol' => '₪'],
                    ]),
                    'duration_days' => 90,
                    'duration_type' => 'days',
                    'is_active' => true,
                    'sort_order' => 2,
                    'features' => [
                        'متابعة حية وشرح مباشر أونلاين طوال الترم',
                        'مدة اشتراك 90 يوماً من أول محاضرة',
                        'تغطية كاملة لمنهج الوزارة',
                        'شهادة إتمام معتمدة',
                        'دعم ومتابعة مستمرة',
                    ],
                ]
            );

            $this->command->info('✅ تم إنشاء باقات كورس البكالوريا بنجاح');
        } else {
            $this->command->warn('⚠️ لم يتم العثور على كورس البكالوريا (ID: 10)');
        }
    }
}
