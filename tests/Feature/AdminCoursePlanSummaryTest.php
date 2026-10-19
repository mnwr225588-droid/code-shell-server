<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CoursePlan;
use App\Models\Lesson;
use App\Models\OnlineLecture;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCoursePlanSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_content_counts_are_current_and_admin_lists_keep_creation_order(): void
    {
        $admin = User::create([
            'name' => 'Plan Summary Admin',
            'first_name' => 'Plan',
            'middle_name' => 'Summary',
            'last_name' => 'Admin',
            'email' => 'plan-summary-admin@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000221',
            'is_admin' => true,
        ]);
        $category = Category::create(['name' => 'Plan Summary Category', 'sort_order' => 1]);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Plan Summary Course',
            'is_free' => true,
            'is_active' => true,
        ]);
        $firstPlan = CoursePlan::create([
            'course_id' => $course->id,
            'name' => 'Created First Plan',
            'slug' => 'created-first-plan',
            'price' => 100,
            'currency' => 'EGP',
            'duration_days' => 30,
            'sort_order' => 50,
            'is_active' => true,
        ]);
        $secondPlan = CoursePlan::create([
            'course_id' => $course->id,
            'name' => 'Created Second Plan',
            'slug' => 'created-second-plan',
            'price' => 100,
            'currency' => 'EGP',
            'duration_days' => 30,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $firstLevel = \App\Models\Level::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'title' => 'Created First Level',
            'order_num' => 20,
        ]);
        $secondLevel = \App\Models\Level::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'title' => 'Created Second Level',
            'order_num' => 1,
        ]);
        Lesson::create([
            'level_id' => $firstLevel->id,
            'title' => 'Created First Lesson',
            'order_num' => 20,
        ]);
        Lesson::create([
            'level_id' => $firstLevel->id,
            'title' => 'Created Second Lesson',
            'order_num' => 1,
        ]);

        $firstGroup = CourseGroup::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'name' => 'Created First Group',
            'capacity' => 10,
        ]);
        CourseGroup::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'name' => 'Created Second Group',
            'capacity' => 10,
        ]);
        $teacher = Teacher::create([
            'name' => 'Plan Summary Teacher',
            'email' => 'plan-summary-teacher@example.com',
            'password' => Hash::make('password123'),
        ]);
        $firstLecture = OnlineLecture::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'level_id' => $firstLevel->id,
            'group_id' => $firstGroup->id,
            'teacher_id' => $teacher->id,
            'title' => 'Created First Lecture',
            'start_date_time' => now()->addDays(5),
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);
        OnlineLecture::create([
            'course_id' => $course->id,
            'plan_id' => $firstPlan->id,
            'level_id' => $firstLevel->id,
            'group_id' => $firstGroup->id,
            'teacher_id' => $teacher->id,
            'title' => 'Created Second Lecture',
            'start_date_time' => now()->addDay(),
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/courses/{$course->id}/plans")
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstPlan->id)
            ->assertJsonPath('data.0.levels_count', 2)
            ->assertJsonPath('data.0.groups_count', 2)
            ->assertJsonPath('data.0.online_lectures_count', 2)
            ->assertJsonPath('data.1.id', $secondPlan->id)
            ->assertJsonPath('data.1.levels_count', 0)
            ->assertJsonPath('data.1.groups_count', 0)
            ->assertJsonPath('data.1.online_lectures_count', 0);

        $this->getJson("/api/levels/{$course->id}?plan_id={$firstPlan->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstLevel->id)
            ->assertJsonPath('data.0.lessons.0.title', 'Created First Lesson')
            ->assertJsonPath('data.0.lessons.1.title', 'Created Second Lesson')
            ->assertJsonPath('data.1.id', $secondLevel->id);

        $this->getJson("/api/admin/courses/{$course->id}/groups?plan_id={$firstPlan->id}")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Created First Group')
            ->assertJsonPath('data.1.name', 'Created Second Group');

        $this->getJson("/api/courses/{$course->id}/online-lectures?plan_id={$firstPlan->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstLecture->id)
            ->assertJsonPath('data.0.title', 'Created First Lecture');
    }
}
