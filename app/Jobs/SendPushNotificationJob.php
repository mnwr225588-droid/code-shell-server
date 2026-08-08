<?php

namespace App\Jobs;

use App\Models\NotificationSend;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public $timeout = 90;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $userIds,
        protected string $title,
        protected string $body,
        protected array $data = [],
        protected ?string $imageUrl = null,
        protected ?int $courseId = null,
        protected string $type = 'general',
        protected ?int $notificationSendId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Started SendPushNotificationJob for " . count($this->userIds) . " users.");

        // Fetch users in chunks or in one query
        $users = User::whereIn('id', $this->userIds)->get();

        $result = PushNotificationService::sendToUsers(
            users: $users,
            title: $this->title,
            body: $this->body,
            data: $this->data,
            imageUrl: $this->imageUrl,
            courseId: $this->courseId,
            type: $this->type
        );

        // Update the NotificationSend record if it exists
        if ($this->notificationSendId) {
            $notificationSend = NotificationSend::find($this->notificationSendId);
            if ($notificationSend) {
                $notificationSend->update([
                    'users_count' => $result['saved'],
                    'fcm_sent'    => $result['fcm_sent'],
                    'no_token'    => $result['no_token'],
                ]);
                Log::info("Updated NotificationSend #{$this->notificationSendId} with results: Saved={$result['saved']}, FCM={$result['fcm_sent']}");
            }
        }
    }
}
