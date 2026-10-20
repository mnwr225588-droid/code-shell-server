<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseSubscription;
use App\Models\LectureAttendance;
use App\Models\OnlineLecture;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherGroupAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_user_login_resolves_teacher_record_for_attendance_read_and_save(): void
    {
        $category = Category::create(['name' => 'Teacher Attendance Test']);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Teacher Attendance Course',
            'is_free' => true,
            'is_active' => true,
        ]);
        Teacher::create([
            'name' => 'Unrelated Teacher',
            'email' => 'unrelated-teacher@example.com',
            'password' => Hash::make('password123'),
        ]);
        $teacher = Teacher::create([
            'name' => 'Teacher Attendance',
            'email' => 'teacher-attendance@example.com',
            'password' => Hash::make('password123'),
        ]);
        $teacherUser = $this->createUser('teacher-attendance@example.com');
        $admin = $this->createUser('teacher-attendance-admin@example.com', true);
        $student = $this->createUser('attendance-class-student@example.com');
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'name' => 'Teacher Attendance Group',
            'capacity' => 10,
        ]);
        $lecture = OnlineLecture::create([
            'course_id' => $course->id,
            'group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'title' => 'Teacher Attendance Lecture',
            'start_date_time' => now()->subHour(),
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'status' => 'ended',
        ]);
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => 'active',
        ]);

        $this->actingAs($teacherUser, 'sanctum')
            ->getJson('/api/teacher/my-groups')
            ->assertOk()
            ->assertJsonPath('data.0.id', $group->id)
            ->assertJsonPath('data.0.students_count', 1)
            ->assertJsonPath('data.0.online_lectures_count', 1);

        $this->actingAs($teacherUser, 'sanctum')
            ->getJson("/api/teacher/groups/{$group->id}/attendance?lecture_id={$lecture->id}")
            ->assertOk()
            ->assertJsonPath('data.students.0.id', $student->id)
            ->assertJsonPath('data.students.0.attendance_status', null);

        $this->actingAs($teacherUser, 'sanctum')
            ->postJson("/api/teacher/groups/{$group->id}/attendance", [
                'lecture_id' => $lecture->id,
                'attendance' => [
                    ['user_id' => $student->id, 'status' => 'absent'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('lecture_attendances', [
            'online_lecture_id' => $lecture->id,
            'user_id' => $student->id,
            'attendance_status' => 'absent',
        ]);

        $this->actingAs($teacherUser, 'sanctum')
            ->getJson("/api/teacher/groups/{$group->id}/attendance?lecture_id={$lecture->id}")
            ->assertOk()
            ->assertJsonPath('data.students.0.attendance_status', 'absent');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/attendance/lectures/{$lecture->id}")
            ->assertOk()
            ->assertJsonPath('data.0.attendance_status', 'absent')
            ->assertJsonPath('summary.absent', 1);
    }

    public function test_absence_after_a_previously_attended_lecture_notifies_admin_once(): void
    {
        $category = Category::create(['name' => 'Attendance Alert Category']);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Attendance Alert Course',
            'is_free' => true,
            'is_active' => true,
        ]);
        $teacher = Teacher::create([
            'name' => 'Attendance Alert Teacher',
            'email' => 'attendance-alert-teacher@example.com',
            'password' => Hash::make('password123'),
        ]);
        $teacherUser = $this->createUser('attendance-alert-teacher@example.com');
        $student = $this->createUser('attendance-alert-student@example.com');
        $previouslyAbsentStudent = $this->createUser('previously-absent-student@example.com');
        $group = CourseGroup::create([
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'name' => 'Attendance Alert Group',
            'capacity' => 10,
        ]);
        $firstLecture = $this->createLecture($course, $group, $teacher, 'Lecture One', now()->subDays(2));
        $secondLecture = $this->createLecture($course, $group, $teacher, 'Lecture Two', now()->subDay());
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => 'active',
        ]);
        CourseSubscription::create([
            'user_id' => $previouslyAbsentStudent->id,
            'course_id' => $course->id,
            'group_id' => $group->id,
            'subscription_status' => 'active',
        ]);
        LectureAttendance::create([
            'online_lecture_id' => $firstLecture->id,
            'user_id' => $student->id,
            'attendance_status' => 'present',
            'join_time' => now()->subDays(2),
        ]);
        LectureAttendance::create([
            'online_lecture_id' => $firstLecture->id,
            'user_id' => $previouslyAbsentStudent->id,
            'attendance_status' => 'absent',
            'join_time' => now()->subDays(2),
        ]);

        Config::set('services.telegram.bot_token', 'student-bot-token');
        Config::set('services.admin_telegram.bot_token', 'separate-admin-bot-token');
        Config::set('services.admin_telegram.chat_id', '987654321');
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $payload = [
            'lecture_id' => $secondLecture->id,
            'attendance' => [
                ['user_id' => $student->id, 'status' => 'absent'],
                ['user_id' => $previouslyAbsentStudent->id, 'status' => 'absent'],
            ],
        ];

        $this->actingAs($teacherUser, 'sanctum')
            ->postJson("/api/teacher/groups/{$group->id}/attendance", $payload)
            ->assertOk();

        Http::assertSent(fn ($request) =>
            str_contains((string) $request['text'], 'تنبيه غياب عن محاضرة')
            && str_contains((string) $request['text'], 'attendance-alert-student@example.com')
            && !str_contains((string) $request['text'], 'previously-absent-student@example.com')
            && str_contains((string) $request['text'], 'Lecture One')
            && str_contains((string) $request['text'], 'Lecture Two')
        );

        $this->actingAs($teacherUser, 'sanctum')
            ->postJson("/api/teacher/groups/{$group->id}/attendance", $payload)
            ->assertOk();

        Http::assertSentCount(1);
    }

    private function createLecture(Course $course, CourseGroup $group, Teacher $teacher, string $title, $startDateTime): OnlineLecture
    {
        return OnlineLecture::create([
            'course_id' => $course->id,
            'group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'title' => $title,
            'start_date_time' => $startDateTime,
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'status' => 'ended',
        ]);
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
