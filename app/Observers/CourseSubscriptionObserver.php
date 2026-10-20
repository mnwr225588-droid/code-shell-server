<?php

namespace App\Observers;

use App\Models\CourseSubscription;
use App\Models\Transaction;
use App\Services\AdminTelegramBotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CourseSubscriptionObserver
{
    public function created(CourseSubscription $subscription): void
    {
        if ($subscription->subscription_status === CourseSubscription::STATUS_ACTIVE) {
            $this->notifyAfterCommit($subscription->id);
        }
    }

    public function updated(CourseSubscription $subscription): void
    {
        if (
            $subscription->wasChanged('subscription_status')
            && $subscription->subscription_status === CourseSubscription::STATUS_ACTIVE
        ) {
            $this->notifyAfterCommit($subscription->id);
        }
    }

    private function notifyAfterCommit(int $subscriptionId): void
    {
        DB::afterCommit(function () use ($subscriptionId): void {
            try {
                $subscription = CourseSubscription::find($subscriptionId);
                if (!$subscription || $subscription->subscription_status !== CourseSubscription::STATUS_ACTIVE) {
                    return;
                }

                app(AdminTelegramBotService::class)->notifySubscription($subscription);
                if ($subscription->group_id) {
                    $group = $subscription->group()->first();
                    if ($group) {
                        app(AdminTelegramBotService::class)->notifyCompletedGroupOnce($group);
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Admin Telegram subscription alert could not be prepared.', [
                    'subscription_id' => $subscriptionId,
                    'exception' => get_class($exception),
                ]);
            }
        });
    }
}
