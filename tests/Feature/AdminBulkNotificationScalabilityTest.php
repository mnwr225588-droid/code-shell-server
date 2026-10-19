<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminBulkNotificationScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_recipient_notification_reports_the_saved_notification_count(): void
    {
        $admin = $this->createUser('single-bulk-admin@example.com', true);
        $student = $this->createUser('single-bulk-student@example.com');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/send-notification', [
                'title' => 'Single student announcement',
                'body' => 'This message should be persisted.',
                'target' => 'email',
                'email' => $student->email,
            ])
            ->assertOk()
            ->assertJson([
                'status' => true,
                'users_count' => 1,
                'fcm_sent' => 0,
                'no_token' => 1,
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Single student announcement',
        ]);
        $this->assertDatabaseHas('notification_sends', [
            'email' => $student->email,
            'users_count' => 1,
            'no_token' => 1,
        ]);
    }

    public function test_bulk_notifications_are_dispatched_in_bounded_batches_without_loading_the_audience(): void
    {
        $admin = $this->createUser('bulk-admin@example.com', true);
        $password = Hash::make('password123');
        $createdAt = now();
        $users = [];

        foreach (range(1, 501) as $number) {
            $users[] = [
                'name' => "Bulk Student {$number}",
                'first_name' => 'Bulk',
                'middle_name' => 'Notification',
                'last_name' => (string) $number,
                'email' => "bulk-student-{$number}@example.com",
                'password' => $password,
                'country' => 'EGP',
                'birth_date' => '2000-01-01',
                'phone' => sprintf('01%09d', $number),
                'is_admin' => false,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }
        foreach (array_chunk($users, 200) as $batch) {
            DB::table('users')->insert($batch);
        }

        Queue::fake();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/send-notification', [
                'title' => 'Load test announcement',
                'body' => 'This message is sent in bounded batches.',
                'target' => 'all',
            ])
            ->assertOk()
            ->assertJson([
                'status' => true,
                'users_count' => 501,
            ]);

        Queue::assertPushed(SendPushNotificationJob::class, 6);
        $this->assertDatabaseHas('notification_sends', [
            'title' => 'Load test announcement',
            'users_count' => 501,
        ]);
    }

    public function test_batched_notification_jobs_accumulate_delivery_results(): void
    {
        $firstStudent = $this->createUser('batch-result-student-1@example.com');
        $secondStudent = $this->createUser('batch-result-student-2@example.com');
        $sentAt = now();
        $sendId = DB::table('notification_sends')->insertGetId([
            'title' => 'Batch result announcement',
            'body' => 'Aggregate results from multiple jobs.',
            'target_type' => 'all',
            'users_count' => 2,
            'fcm_sent' => 0,
            'no_token' => 0,
            'sent_at' => $sentAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);

        foreach ([$firstStudent, $secondStudent] as $student) {
            (new SendPushNotificationJob(
                [$student->id],
                'Batch result announcement',
                'Aggregate results from multiple jobs.',
                notificationSendId: $sendId,
            ))->handle();
        }

        $this->assertDatabaseHas('notification_sends', [
            'id' => $sendId,
            'users_count' => 2,
            'fcm_sent' => 0,
            'no_token' => 2,
        ]);
        $this->assertDatabaseCount('notifications', 2);
    }

    private function createUser(string $email, bool $isAdmin = false): User
    {
        return User::create([
            'name' => 'Bulk Notification Admin',
            'first_name' => 'Bulk',
            'middle_name' => 'Notification',
            'last_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password123'),
            'country' => 'EGP',
            'birth_date' => '2000-01-01',
            'phone' => '01000000771',
            'is_admin' => $isAdmin,
        ]);
    }
}
