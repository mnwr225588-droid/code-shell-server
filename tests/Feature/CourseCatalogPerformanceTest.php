<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourseCatalogPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_catalog_serialization_does_not_run_per_course_count_queries(): void
    {
        Cache::flush();

        $category = Category::create(['name' => 'Catalog performance']);
        $user = User::create([
            'name' => 'Catalog Student',
            'first_name' => 'Catalog',
            'middle_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'catalog-student@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000000',
        ]);

        foreach (range(1, 4) as $number) {
            Course::create([
                'category_id' => $category->id,
                'title' => "Catalog Course {$number}",
                'thumbnail' => "courses/course-{$number}.png",
                'is_free' => false,
                'price' => 100,
                'is_active' => true,
            ]);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/courses');

        $response->assertOk();
        $this->assertNotEmpty($response->json('catalog_version'));
        $data = collect($response->json('data'));
        $this->assertGreaterThanOrEqual(4, $data->count());

        $course = $data->firstWhere('title', 'Catalog Course 4');
        $this->assertNotNull($course);
        $this->assertSame(0, $course['levels_count']);
        $this->assertSame(0, $course['lessons_count']);
        $this->assertSame(120, $course['students_count']);
        $this->assertSame(0, $course['subscriptions_count']);
        $this->assertSame(0, $course['reservations_count']);

        $versionResponse = $this->getJson('/api/courses/catalog-version');
        $versionResponse->assertOk();
        $this->assertSame($response->json('catalog_version'), $versionResponse->json('catalog_version'));

        $perCourseCountQueries = array_filter($queries, fn (string $sql) =>
            str_contains($sql, 'from "levels" where "course_id" = ?')
            || str_contains($sql, 'from "lessons" where "level_id" in')
            || (
                str_contains($sql, 'from "users" inner join "course_subscriptions"')
                && str_contains($sql, '"course_subscriptions"."course_id" = ?')
            )
            || str_contains($sql, 'from "course_reservations" where "course_id" = ?')
        );

        $this->assertCount(0, $perCourseCountQueries, implode("\n", $perCourseCountQueries));
    }
}
