<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\CoursePlan;
use App\Models\CourseSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOpsSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_topup_requires_the_operation_password(): void
    {
        Config::set('services.admin_ops.password', 'test-operations-password');
        $admin = $this->createUser('Admin');
        $student = $this->createUser('Student');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/wallet/admin-credit', [
                'user_id' => $student->id,
                'amount' => 75,
                'admin_password' => 'wrong-password',
            ])
            ->assertForbidden();
        $this->assertSame(0.0, (float) $student->fresh()->wallet_balance);

        $this->postJson('/api/admin/wallet/admin-credit', [
            'user_id' => $student->id,
            'amount' => 75,
            'notes' => 'Authorized test topup',
            'admin_password' => 'test-operations-password',
        ])->assertOk()->assertJsonPath('wallet_balance', 75);

        $this->assertSame(75.0, (float) $student->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $student->id,
            'type' => 'credit',
            'amount' => 75,
        ]);
        $this->assertDatabaseHas('admin_operations', [
            'operation' => 'topup',
            'user_id' => $student->id,
            'amount' => 75,
        ]);
    }

    public function test_admin_can_delete_operation_history_without_reversing_the_operation(): void
    {
        Config::set('services.admin_ops.password', 'test-operations-password');
        $admin = $this->createUser('Admin');
        $student = $this->createUser('Student');
        $student->wallet_balance = 125;
        $student->save();
        $operationId = DB::table('admin_operations')->insertGetId([
            'operation' => 'topup',
            'admin_id' => $admin->id,
            'user_id' => $student->id,
            'user_email' => $student->email,
            'amount' => 125,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/operations/{$operationId}", [
                'admin_password' => 'wrong-password',
            ])
            ->assertForbidden();
        $this->assertDatabaseHas('admin_operations', ['id' => $operationId]);

        $this->deleteJson("/api/admin/operations/{$operationId}", [
            'admin_password' => 'test-operations-password',
        ])->assertOk()->assertJsonPath('status', true);

        $this->assertDatabaseMissing('admin_operations', ['id' => $operationId]);
        $this->assertSame(125.0, (float) $student->fresh()->wallet_balance);
    }

    public function test_admin_subscription_activates_the_selected_plan_and_allows_another_plan(): void
    {
        Config::set('services.admin_ops.password', 'test-operations-password');
        $admin = $this->createUser('Admin');
        $student = $this->createUser('Student');
        $category = Category::create(['name' => 'Admin Ops Category', 'sort_order' => 1]);
        $course = Course::create([
            'category_id' => $category->id,
            'title' => 'Admin Ops Course',
            'is_free' => false,
            'is_active' => true,
        ]);
        $otherCourse = Course::create([
            'category_id' => $category->id,
            'title' => 'Other Admin Ops Course',
            'is_free' => false,
            'is_active' => true,
        ]);
        $selectedPlan = $this->createPlan($course, 'Selected Plan', 149, 45);
        $secondPlan = $this->createPlan($course, 'Second Plan', 299, 90);
        $foreignPlan = $this->createPlan($otherCourse, 'Foreign Plan', 99, 30);
        $inactivePlan = $this->createPlan($course, 'Inactive Plan', 49, 30, false);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/subscriptions/admin-subscribe', [
                'user_id' => $student->id,
                'course_id' => $course->id,
                'plan_id' => $selectedPlan->id,
                'admin_password' => 'test-operations-password',
            ])
            ->assertOk()
            ->assertJson([
                'status' => true,
                'plan_id' => $selectedPlan->id,
                'amount' => 149,
                'currency_code' => 'EGP',
            ]);

        $subscription = CourseSubscription::where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where('plan_id', $selectedPlan->id)
            ->firstOrFail();
        $this->assertSame(CourseSubscription::STATUS_ACTIVE, $subscription->subscription_status);
        $this->assertSame(149.0, (float) $subscription->amount);
        $this->assertNotNull($subscription->started_at);
        $this->assertNotNull($subscription->expired_at);
        $this->assertNotNull($subscription->group_id);

        $this->postJson('/api/admin/subscriptions/admin-subscribe', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'plan_id' => $selectedPlan->id,
            'admin_password' => 'test-operations-password',
        ])->assertStatus(422);

        $this->postJson('/api/admin/subscriptions/admin-subscribe', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'plan_id' => $secondPlan->id,
            'admin_password' => 'test-operations-password',
        ])->assertOk()->assertJsonPath('plan_id', $secondPlan->id);

        foreach ([$foreignPlan, $inactivePlan] as $unavailablePlan) {
            $this->postJson('/api/admin/subscriptions/admin-subscribe', [
                'user_id' => $student->id,
                'course_id' => $course->id,
                'plan_id' => $unavailablePlan->id,
                'admin_password' => 'test-operations-password',
            ])->assertStatus(422);
        }

        $this->assertSame(2, CourseSubscription::where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->count());
    }

    private function createUser(string $label): User
    {
        $suffix = uniqid();

        return User::create([
            'name' => $label,
            'first_name' => $label,
            'middle_name' => 'Ops',
            'last_name' => 'Test',
            'email' => strtolower($label) . "-{$suffix}@example.com",
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '010' . random_int(10000000, 99999999),
            'is_admin' => $label === 'Admin',
        ]);
    }

    private function createPlan(Course $course, string $name, float $price, int $duration, bool $active = true): CoursePlan
    {
        return CoursePlan::create([
            'course_id' => $course->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'price' => $price,
            'currency' => 'EGP',
            'duration_days' => $duration,
            'is_active' => $active,
        ]);
    }
}
