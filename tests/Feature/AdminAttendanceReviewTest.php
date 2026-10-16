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

class AdminAttendanceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_review_ended_group_lectures_and_saved_student_attendance(): void
    {
        [$admin, $group, $lecture, $students] = $this->createAttendanceRecords();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/attendance/groups')
            ->assertOk()
            ->assertJsonPath('data.0.ended_lectures_count', 1)
            ->assertJsonPath('data.0.students_count', 3);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/attendance/groups/{$group->id}/ended-lectures")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lecture->id);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/attendance/lectures/{$lecture->id}")
            ->assertOk()
            ->assertJsonPath('summary.present', 1)
            ->assertJsonPath('summary.absent', 1)
            ->assertJsonPath('summary.unmarked', 1)
            ->assertJsonPath('data.0.attendance_status', 'present')
            ->assertJsonPath('data.1.attendance_status', 'absent')
            ->assertJsonPath('data.2.attendance_status', null)
            ->assertJsonPath('data.0.email', $students[0]->email);
    }

    public function test_non_admin_cannot_review_attendance(): void
    {
        [, $group] = $this->createAttendanceRecords();
        $student = User::where('email', 'attendance-student-1@example.com')->firstOrFail();

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/admin/attendance/groups')
            ->assertForbidden();

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/admin/attendance/groups/{$group->id}/ended-lectures")
            ->assertForbidden();
    }

    private function createAttendanceRecords(): array
    {
        $admin = $this->createUser('attendance-admin@example.com', true);
        $students = [
            $this->createUser('attendance-student-1@example.com'),
            $this->createUser('attendance-student-2@example.com'),
            $this->createUser('attendance-student-3@example.com'),
        ];
        $category = Category::create(['name' => 'Attendance Test Category', 'sort_order' => 1]);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Attendance Test Course',
            'is_free' => true,
            'is_active' => true,
        ]);
        $teacher = Teacher::create([
            'name' => 'Attendance Test Teacher',
            'email' => 'attendance-teacher@example.com',
            'password' => Hash::make('password123'),
        ]);
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'name' => 'Attendance Test Group',
            'capacity' => 10,
        ]);

        foreach ($students as $student) {
            \App\Models\CourseSubscription::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'group_id' => $group->id,
                'subscription_status' => 'active',
            ]);
        }

        $lecture = OnlineLecture::create([
            'course_id' => $course->id,
            'group_id' => $group->id,
            'teacher_id' => $admin->id,
            'title' => 'Ended Attendance Test Lecture',
            'start_date_time' => now()->subDay(),
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'status' => 'ended',
        ]);
        OnlineLecture::create([
            'course_id' => $course->id,
            'group_id' => $group->id,
            'teacher_id' => $admin->id,
            'title' => 'Scheduled Attendance Test Lecture',
            'start_date_time' => now()->addDay(),
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'status' => 'scheduled',
        ]);

        foreach (['present', 'absent'] as $index => $status) {
            \App\Models\LectureAttendance::create([
                'online_lecture_id' => $lecture->id,
                'user_id' => $students[$index]->id,
                'attendance_status' => $status,
                'join_time' => now(),
            ]);
        }

        return [$admin, $group, $lecture, $students];
    }

    private function createUser(string $email, bool $isAdmin = false): User
    {
        return User::create([
            'name' => 'Attendance Test User',
            'first_name' => 'Attendance',
            'middle_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000001',
            'is_admin' => $isAdmin,
        ]);
    }
}
