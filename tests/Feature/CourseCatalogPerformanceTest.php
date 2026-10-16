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

    public function test_course_catalog_handles_json_string_prices_in_a_previously_cached_payload(): void
    {
        Cache::flush();

        $category = Category::create(['name' => 'Catalog price cache']);
        $user = User::create([
            'name' => 'Catalog Price Student',
            'first_name' => 'Catalog',
            'middle_name' => 'Price',
            'last_name' => 'Student',
            'email' => 'catalog-price-student@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000001',
        ]);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Course With Cached JSON Prices',
            'prices' => ['EGP' => 425],
            'is_free' => false,
            'price' => 425,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/courses')->assertOk();

        $cacheVersion = Cache::get('courses_index_cache_version', 'v1');
        $cacheKey = 'courses_index_v4_' . $cacheVersion . '_catalog';
        $cachedCourses = collect(Cache::get($cacheKey))
            ->map(function (array $cachedCourse) use ($course) {
                if ((int) $cachedCourse['id'] === $course->id) {
                    $cachedCourse['prices'] = '{"EGP":425}';
                }
                return $cachedCourse;
            })
            ->all();
        Cache::put($cacheKey, $cachedCourses, 300);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/courses');

        $response->assertOk();
        $courseData = collect($response->json('data'))->firstWhere('id', $course->id);
        $this->assertSame(['EGP' => 425], $courseData['prices']);
        $this->assertEquals(425.0, $courseData['price']);
    }

    public function test_course_catalog_rebuilds_malformed_cached_course_entries(): void
    {
        Cache::flush();

        $category = Category::create(['name' => 'Malformed Catalog Cache']);
        $user = User::create([
            'name' => 'Malformed Cache Student',
            'first_name' => 'Malformed',
            'middle_name' => 'Cache',
            'last_name' => 'Student',
            'email' => 'malformed-cache-student@example.com',
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000002',
        ]);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Valid Catalog Course',
            'prices' => ['EGP' => 350],
            'is_free' => false,
            'price' => 350,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/courses')->assertOk();

        $cacheVersion = Cache::get('courses_index_cache_version', 'v1');
        $cacheKey = 'courses_index_v4_' . $cacheVersion . '_catalog';
        $cachedCourses = Cache::get($cacheKey);
        $cachedCourses->push('malformed cached row');
        Cache::put($cacheKey, $cachedCourses, 300);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonFragment(['id' => $course->id, 'title' => 'Valid Catalog Course']);
        $this->assertNotContains('malformed cached row', $response->json('data'));
        $this->assertNotContains('malformed cached row', Cache::get($cacheKey)->all());
    }
}
