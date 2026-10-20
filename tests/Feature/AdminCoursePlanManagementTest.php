<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CoursePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCoursePlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_existing_and_new_plans_when_course_lookup_resolves_legacy_id(): void
    {
        Cache::flush();

        $admin = $this->createUser('plan-management-admin@example.com', true);
        $student = $this->createUser('plan-management-student@example.com');
        $course = Course::findBaccalaureateCourse();
        $this->assertNotNull($course);
        $monthlyPlan = $this->createPlan($course, 'اشتراك شهري', 'management-monthly-' . Str::random(8), 300, 30);
        $termPlan = $this->createPlan($course, 'اشتراك الترم', 'management-term-' . Str::random(8), 800, 90);
        $legacyCourseId = $course->id + 5000;

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/courses/{$legacyCourseId}/plans")
            ->assertOk()
            ->assertJsonPath('data.0.id', $monthlyPlan->id)
            ->assertJsonPath('data.1.id', $termPlan->id);

        $this->postJson("/api/admin/plans/{$monthlyPlan->id}", [
            'name' => 'اشتراك شهري محدث',
            'description' => 'تفاصيل الباقة بعد التعديل',
            'what_you_will_learn' => 'محتوى المنهج',
            'duration_days' => 45,
            'currency' => 'EGP',
            'is_active' => '1',
            'is_free' => '0',
            'has_discount' => '1',
            'original_price' => 400,
            'discount_price' => 300,
            'price' => 300,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'اشتراك شهري محدث')
            ->assertJsonPath('data.duration_days', 45);

        $createdPlan = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/courses/{$legacyCourseId}/plans", [
                'name' => 'باقة جديدة',
                'price' => 500,
                'duration_days' => 60,
                'currency' => 'EGP',
                'is_active' => '1',
                'is_free' => '0',
                'has_discount' => '0',
            ])
            ->assertOk()
            ->assertJsonPath('data.course_id', $course->id)
            ->json('data');

        $this->assertDatabaseHas('course_plans', [
            'id' => $createdPlan['id'],
            'course_id' => $course->id,
            'name' => 'باقة جديدة',
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/courses/{$legacyCourseId}/plans")
            ->assertOk()
            ->assertJsonPath('course.id', $course->id)
            ->assertJsonPath('data.0.name', 'اشتراك شهري محدث')
            ->assertJsonPath('data.2.name', 'باقة جديدة')
            ->assertJsonPath('data.2.duration_days', 60);
    }

    private function createPlan(Course $course, string $name, string $slug, float $price, int $durationDays): CoursePlan
    {
        return CoursePlan::create([
            'course_id' => $course->id,
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'original_price' => $price,
            'discount_price' => $price,
            'currency' => 'EGP',
            'duration_days' => $durationDays,
            'is_active' => true,
        ]);
    }

    private function createUser(string $email, bool $isAdmin = false): User
    {
        return User::create([
            'name' => 'Plan Management User',
            'first_name' => 'Plan',
            'middle_name' => 'Management',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'country' => 'EG',
            'birth_date' => '2000-01-01',
            'phone' => '01000000321',
            'is_admin' => $isAdmin,
        ]);
    }
}
