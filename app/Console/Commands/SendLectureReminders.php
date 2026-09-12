<?php

namespace App\Console\Commands;

use App\Models\OnlineLecture;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendLectureReminders extends Command
{
    protected $signature = 'lectures:send-reminders';
    protected $description = 'Send push notifications 20 minutes before lecture starts';

    public function handle()
    {
        $now = Carbon::now('UTC');
        $windowStart = $now->copy()->addMinutes(19);
        $windowEnd = $now->copy()->addMinutes(21);

        $lectures = OnlineLecture::where('status', 'scheduled')
            ->whereBetween('start_date_time', [$windowStart, $windowEnd])
            ->with('group')
            ->get();

        foreach ($lectures as $lecture) {
            try {
                // Get all students in this group
                $userIds = \DB::table('course_subscriptions')
                    ->where('group_id', $lecture->group_id)
                    ->pluck('user_id')
                    ->unique()
                    ->values()
                    ->toArray();

                if (empty($userIds)) {
                    $this->info("Lecture #{$lecture->id}: no students in group #{$lecture->group_id}");
                    continue;
                }

                $title = 'محاضرة بعد 20 دقيقة 📢';
                $body = "محاضرة \"{$lecture->title}\" ستبدأ بعد 20 دقيقة. جهّز نفسك!";

                \App\Jobs\SendPushNotificationJob::dispatch(
                    $userIds,
                    $title,
                    $body,
                    [
                        'type' => 'lecture_reminder',
                        'lecture_id' => (string) $lecture->id,
                        'course_id' => (string) $lecture->course_id,
                    ],
                    null,
                    $lecture->course_id,
                    'lecture_reminder',
                    null
                );

                $this->info("Lecture #{$lecture->id}: sent reminder to " . count($userIds) . " students.");
                Log::info("Lecture reminder sent for lecture #{$lecture->id} to " . count($userIds) . " students.");

            } catch (\Throwable $e) {
                Log::error("Failed to send reminder for lecture #{$lecture->id}: " . $e->getMessage());
                $this->error("Lecture #{$lecture->id}: " . $e->getMessage());
            }
        }

        $this->info('Lecture reminders check completed.');
        return 0;
    }
}
