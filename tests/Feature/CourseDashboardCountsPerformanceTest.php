<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourseDashboardCountsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_course_lists_aggregate_appended_counts_without_per_course_queries(): void
    {
        $admin = User::create([
            'name' => 'Dashboard Admin',
            'first_name' => 'Dashboard',
            'middle_name' => 'Count',
            'last_name' => 'Admin',
            'email' => 'dashboard-admin@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000231',
            'is_admin' => true,
        ]);
        $student = User::create([
            'name' => 'Dashboard Student',
            'first_name' => 'Dashboard',
            'middle_name' => 'Count',
            'last_name' => 'Student',
            'email' => 'dashboard-student@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000232',
        ]);
        $category = Category::create(['name' => 'Dashboard Count Category', 'sort_order' => 1]);
        $courses = collect(range(1, 4))->map(fn (int $index) => Course::create([
            'category_id' => $category->id,
            'title' => "Dashboard Count Course {$index}",
            'is_free' => true,
            'is_active' => true,
        ]));
        $level = Level::create([
            'course_id' => $courses[0]->id,
            'title' => 'Dashboard Count Level',
            'order_num' => 1,
        ]);
        Lesson::create([
            'level_id' => $level->id,
            'title' => 'Dashboard Count Lesson',
            'order_num' => 1,
        ]);
        CourseSubscription::create([
            'user_id' => $student->id,
            'course_id' => $courses[0]->id,
            'subscription_status' => 'active',
        ]);
        DB::table('course_reservations')->insert([
            'user_id' => $student->id,
            'course_id' => $courses[0]->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            '/api/admin/reservations',
            '/api/admin/subscriptions',
        ] as $endpoint) {
            $queries = [];
            DB::listen(function ($query) use (&$queries) {
                $queries[] = strtolower($query->sql);
            });

            $response = $this->actingAs($admin, 'sanctum')->getJson($endpoint)->assertOk();
            $firstCourse = collect($response->json('data'))->firstWhere('id', $courses[0]->id);
            $this->assertNotNull($firstCourse);
            $this->assertSame($courses[0]->id, $firstCourse['id']);
            $this->assertSame(1, $firstCourse['levels_count']);
            $this->assertSame(1, $firstCourse['lessons_count']);
            $this->assertSame(1, $firstCourse['reservations_count']);
            $this->assertSame(1, $firstCourse['subscriptions_count']);
            $this->assertSame(121, $firstCourse['students_count']);

            $repeatedCountQueries = array_filter($queries, fn (string $sql) =>
                preg_match('/from ["`]?levels["`]? where ["`]?course_id["`]? = \?/', $sql)
                || preg_match('/from ["`]?course_reservations["`]? where ["`]?course_id["`]? = \?/', $sql)
                || preg_match('/from ["`]?course_subscriptions["`]? where ["`]?course_id["`]? = \?/', $sql)
            );
            $this->assertCount(0, $repeatedCountQueries, implode("\n", $repeatedCountQueries));
        }
    }
}
