<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\OnlineLecture;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOnlineLectureSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_schedule_lecture_with_browser_date_and_time_values(): void
    {
        [$user, $course, $group, $teacher] = $this->createSchedulingRecords();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/online-lectures', [
                'course_id' => $course->id,
                'group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'title' => 'Scheduled Test Lecture',
                'start_date' => '2026-10-16',
                'start_time' => '18:30',
                'timezone' => 'Africa/Cairo',
                'duration_minutes' => 60,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $lecture = OnlineLecture::firstOrFail();
        $this->assertSame('2026-10-16 15:30:00', $lecture->start_date_time->format('Y-m-d H:i:s'));
        $this->assertSame('Africa/Cairo', $lecture->timezone);
    }

    public function test_invalid_lecture_date_and_time_are_rejected_instead_of_silently_changed(): void
    {
        [$user, $course, $group, $teacher] = $this->createSchedulingRecords();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/online-lectures', [
                'course_id' => $course->id,
                'group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'title' => 'Invalid Test Lecture',
                'start_date' => '2026-13-40',
                'start_time' => '25:70',
                'timezone' => 'Africa/Cairo',
                'duration_minutes' => 60,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'start_time']);
        $this->assertDatabaseCount('online_lectures', 0);
    }

    /**
     * @return array{User, Course, CourseGroup, Teacher}
     */
    private function createSchedulingRecords(): array
    {
        $user = User::create([
            'name' => 'Admin Scheduling Test',
            'first_name' => 'Admin',
            'middle_name' => 'Scheduling',
            'last_name' => 'Test',
            'email' => 'lecture-admin@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000001',
            'is_admin' => true,
        ]);

        $category = Category::create([
            'name' => 'Lecture Scheduling Test Category',
            'sort_order' => 1,
        ]);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Lecture Scheduling Test Course',
            'is_free' => false,
            'price' => 100,
            'is_active' => true,
        ]);
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'name' => 'Lecture Scheduling Test Group',
            'capacity' => 10,
            'status' => 'open_for_registration',
        ]);
        $teacher = Teacher::create([
            'name' => 'Lecture Scheduling Test Teacher',
            'email' => 'lecture-teacher@example.com',
            'password' => Hash::make('password123'),
        ]);

        return [$user, $course, $group, $teacher];
    }
}
