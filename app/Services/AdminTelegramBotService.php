<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseSubscription;
use App\Models\OnlineLecture;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminTelegramBotService
{
    public const SHORTCUT_STATS = '📊 الإحصائيات';
    public const SHORTCUT_GROUPS = '👥 المجموعات';
    public const SHORTCUT_MENU = '🏠 القائمة الرئيسية';

    public function sendMessage(string $message, ?array $replyMarkup = null): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Admin Telegram alert was not sent because its separate bot token or private chat ID is missing or invalid.');
            return false;
        }

        try {
            $payload = [
                'chat_id' => (string) config('services.admin_telegram.chat_id'),
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];
            if ($replyMarkup !== null) {
                $payload['reply_markup'] = $replyMarkup;
            }

            $response = Http::asJson()
                ->timeout(5)
                ->connectTimeout(2)
                ->post($this->apiUrl('sendMessage'), $payload);

            if (!$response->successful() || $response->json('ok') !== true) {
                Log::warning('Admin Telegram alert delivery failed.', [
                    'http_status' => $response->status(),
                    'telegram_error_code' => $response->json('error_code'),
                    'telegram_description' => $response->json('description'),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Admin Telegram alert delivery failed.', [
                'exception' => get_class($exception),
            ]);
            return false;
        }
    }

    public function isConfigured(): bool
    {
        $token = (string) config('services.admin_telegram.bot_token');
        $chatId = (string) config('services.admin_telegram.chat_id');

        return $token !== ''
            && $chatId !== ''
            && preg_match('/^\d+$/', $chatId) === 1
            && !hash_equals((string) config('services.telegram.bot_token'), $token);
    }

    public function answerCallback(string $callbackId, ?string $text = null, bool $showAlert = false): void
    {
        $payload = ['callback_query_id' => $callbackId];
        if ($text !== null) {
            $payload['text'] = $text;
            $payload['show_alert'] = $showAlert;
        }

        $this->callTelegram('answerCallbackQuery', $payload);
    }

    public function sendAdminMenu(): bool
    {
        return $this->sendMessage(
            "🛠️ <b>لوحة إدارة Code Shell</b>\nاختر القسم الذي تريد عرضه:",
            ['inline_keyboard' => [[
                ['text' => '📊 عرض الإحصائيات', 'callback_data' => 'admin:stats'],
                ['text' => '👥 عرض المجموعات', 'callback_data' => 'admin:courses:0'],
            ]]]
        );
    }

    public function sendStatistics(): bool
    {
        $activeSubscriptions = CourseSubscription::query()->where(function ($query) {
            $query->whereNull('subscription_status')
                ->orWhere('subscription_status', CourseSubscription::STATUS_ACTIVE);
        });
        $registeredAccounts = User::where('is_admin', false)->count();
        $activeSubscribers = (clone $activeSubscriptions)
            ->distinct('user_id')
            ->count('user_id');
        $activeSubscriptionCount = (clone $activeSubscriptions)->count();
        $courseCount = Course::where('is_active', true)->count();
        $groupCount = CourseGroup::count();
        $openGroups = CourseGroup::whereIn('status', ['open_for_registration', 'waiting_for_students', 'ready_to_start'])->count();
        $activeGroups = CourseGroup::where('status', 'active')->count();
        $completedGroups = CourseGroup::where('status', 'completed')->count();
        $completedPayments = Transaction::where('status', Transaction::STATUS_COMPLETED);
        $completedPaymentCount = (clone $completedPayments)->count();
        $completedPaymentTotal = (float) (clone $completedPayments)->sum('amount');
        $walletBalance = (float) User::where('is_admin', false)->sum('wallet_balance');

        $message = "📊 <b>إحصائيات منصة Code Shell</b>\n\n"
            . '👤 حسابات الطلاب المسجلة: <b>' . number_format($registeredAccounts) . "</b>\n"
            . '🎓 الطلاب ذوو الاشتراك النشط: <b>' . number_format($activeSubscribers) . "</b>\n"
            . '✅ الاشتراكات النشطة أو القديمة: <b>' . number_format($activeSubscriptionCount) . "</b>\n"
            . '📚 الكورسات المنشورة: <b>' . number_format($courseCount) . "</b>\n"
            . '👥 المجموعات: <b>' . number_format($groupCount) . "</b>\n"
            . '🟢 مجموعات التسجيل: <b>' . number_format($openGroups) . "</b>\n"
            . '▶️ مجموعات مفعلة: <b>' . number_format($activeGroups) . "</b>\n"
            . '🏁 مجموعات مكتملة: <b>' . number_format($completedGroups) . "</b>\n"
            . '💳 عمليات دفع مكتملة: <b>' . number_format($completedPaymentCount) . "</b>\n"
            . '💰 إجمالي المدفوعات المكتملة: <b>' . number_format($completedPaymentTotal, 2) . " EGP</b>\n"
            . '👛 أرصدة محافظ الطلاب الحالية: <b>' . number_format($walletBalance, 2) . " EGP</b>\n\n"
            . '🕒 آخر تحديث: ' . $this->escape(now()->toDateTimeString());

        return $this->sendMessage($message, ['inline_keyboard' => [[
            ['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'admin:menu'],
        ]]]);
    }

    public function sendCourseList(int $page = 0): bool
    {
        $perPage = 8;
        $courses = Course::query()
            ->withCount('groups')
            ->orderBy('id')
            ->skip(max(0, $page) * $perPage)
            ->take($perPage + 1)
            ->get();
        $hasNextPage = $courses->count() > $perPage;
        $courses = $courses->take($perPage);

        if ($courses->isEmpty() && $page > 0) {
            return $this->sendMessage('لا توجد كورسات أخرى في هذه الصفحة.');
        }

        $keyboard = $courses->map(fn (Course $course) => [[
            'text' => mb_strimwidth((string) $course->title, 0, 48, '…')
                . ' (' . $course->groups_count . ' مجموعات)',
            'callback_data' => 'admin:course:' . $course->id . ':0',
        ]])->all();
        $navigation = [];
        if ($page > 0) {
            $navigation[] = ['text' => '⬅️ السابق', 'callback_data' => 'admin:courses:' . ($page - 1)];
        }
        if ($hasNextPage) {
            $navigation[] = ['text' => 'التالي ➡️', 'callback_data' => 'admin:courses:' . ($page + 1)];
        }
        if ($navigation) {
            $keyboard[] = $navigation;
        }
        $keyboard[] = [['text' => '🔙 القائمة الرئيسية', 'callback_data' => 'admin:menu']];

        return $this->sendMessage(
            '📚 <b>الكورسات والمجموعات</b>' . "\nاختر كورساً لعرض مجموعاته. (الصفحة " . ($page + 1) . ')',
            ['inline_keyboard' => $keyboard]
        );
    }

    public function sendCourseGroups(int $courseId, int $page = 0): bool
    {
        $course = Course::find($courseId);
        if (!$course) {
            return $this->sendMessage('لم يتم العثور على هذا الكورس.');
        }

        $perPage = 6;
        $groups = CourseGroup::query()
            ->where('course_id', $courseId)
            ->with('plan')
            ->addSelect([
                'active_students_count' => DB::table('course_subscriptions')
                    ->selectRaw('COUNT(DISTINCT user_id)')
                    ->whereColumn('course_subscriptions.group_id', 'course_groups.id')
                    ->where(function ($query) {
                        $query->whereNull('subscription_status')
                            ->orWhere('subscription_status', CourseSubscription::STATUS_ACTIVE);
                    }),
            ])
            ->orderBy('id')
            ->skip(max(0, $page) * $perPage)
            ->take($perPage + 1)
            ->get();
        $hasNextPage = $groups->count() > $perPage;
        $groups = $groups->take($perPage);

        $lines = [
            '👥 <b>مجموعات كورس: ' . $this->escape($course->title) . '</b>',
            'إجمالي المجموعات: <b>' . CourseGroup::where('course_id', $courseId)->count() . '</b>',
            '',
        ];
        $keyboard = [];
        foreach ($groups as $group) {
            $count = (int) $group->active_students_count;
            $capacity = max(1, (int) $group->capacity);
            $lines[] = '• <b>' . $this->escape($group->name) . '</b> — '
                . $this->escape($this->groupStatusLabel($group->status))
                . ' — ' . $count . '/' . $capacity . ' طالب'
                . ($group->plan ? ' — ' . $this->escape($group->plan->name) : '');

            if (
                $count >= $capacity
                && !in_array($group->status, ['active', 'completed'], true)
            ) {
                $keyboard[] = [[
                    'text' => '▶️ تفعيل ' . mb_strimwidth((string) $group->name, 0, 32, '…'),
                    'callback_data' => 'admin:activate:' . $group->id,
                ]];
            }
        }

        if ($groups->isEmpty()) {
            $lines[] = 'لا توجد مجموعات مسجلة لهذا الكورس.';
        }
        $navigation = [];
        if ($page > 0) {
            $navigation[] = ['text' => '⬅️ السابق', 'callback_data' => 'admin:course:' . $courseId . ':' . ($page - 1)];
        }
        if ($hasNextPage) {
            $navigation[] = ['text' => 'التالي ➡️', 'callback_data' => 'admin:course:' . $courseId . ':' . ($page + 1)];
        }
        if ($navigation) {
            $keyboard[] = $navigation;
        }
        $keyboard[] = [['text' => '🔙 قائمة الكورسات', 'callback_data' => 'admin:courses:0']];

        return $this->sendMessage(
            implode("\n", $lines) . "\n\nالصفحة " . ($page + 1),
            ['inline_keyboard' => $keyboard]
        );
    }

    public function activateFullGroup(int $groupId): string
    {
        $group = CourseGroup::query()->with('course')->find($groupId);
        if (!$group) {
            return 'لم يتم العثور على المجموعة.';
        }
        if (in_array($group->status, ['active', 'completed'], true)) {
            return 'المجموعة مفعلة بالفعل أو مكتملة.';
        }

        $memberCount = $this->activeGroupMemberships($groupId)->count();
        $capacity = max(1, (int) $group->capacity);
        if ($memberCount < $capacity) {
            return "لم تكتمل المجموعة بعد ({$memberCount}/{$capacity})، لذلك لم يتم تفعيلها.";
        }

        CourseGroupService::activateAndSpawnNext($group);
        $group->refresh();
        $nextGroup = CourseGroup::query()
            ->where('course_id', $group->course_id)
            ->when($group->plan_id, fn ($query) => $query->where('plan_id', $group->plan_id), fn ($query) => $query->whereNull('plan_id'))
            ->whereIn('status', ['open_for_registration', 'waiting_for_students', 'ready_to_start'])
            ->orderByDesc('id')
            ->first();

        return '✅ تم تفعيل مجموعة ' . $this->escape($group->name)
            . ' في كورس ' . $this->escape($group->course?->title ?: 'غير معروف')
            . ($nextGroup && $nextGroup->id !== $group->id
                ? "\nتم فتح مجموعة التسجيل التالية: <b>" . $this->escape($nextGroup->name) . '</b>.'
                : '');
    }

    public function sendTestMessage(): bool
    {
        return $this->sendMessage(
            "✅ <b>تم اختبار بوت الإدارة</b>\n"
            . 'البوت الإداري المستقل متصل ويستقبل إشعارات Code Shell.'
            . "\n🕒 " . e(now()->toDateTimeString())
        );
    }

    public function notifySubscription(CourseSubscription $subscription, ?Transaction $transaction = null): bool
    {
        $subscription->loadMissing(['user', 'course', 'plan', 'group']);
        $transaction ??= $this->findCompletedTransaction($subscription);

        $student = $subscription->user;
        $course = $subscription->course;
        $plan = $subscription->plan;
        $group = $subscription->group;
        $membership = $group ? $this->groupMembershipPosition($group->id, (int) $subscription->user_id) : null;

        $lines = [
            '🎓 <b>اشتراك جديد تم تفعيله</b>',
            '',
            '👤 <b>الطالب:</b> ' . $this->escape($student?->name ?: 'غير معروف'),
            '🆔 <b>رقم الطالب:</b> ' . $this->escape($subscription->user_id),
            '📧 <b>البريد:</b> ' . $this->escape($student?->email ?: 'غير مسجل'),
            '📱 <b>الهاتف:</b> ' . $this->escape($student?->phone ?: 'غير مسجل'),
            '📚 <b>الكورس:</b> ' . $this->escape($course?->title ?: 'غير معروف'),
            '📦 <b>الباقة:</b> ' . $this->escape($plan?->name ?: 'اشتراك مباشر / بدون باقة'),
            '👥 <b>المجموعة:</b> ' . $this->escape($group?->name ?: 'لم تُحدد'),
            '🔢 <b>ترتيب العضو في المجموعة:</b> ' . ($membership ? $membership['position'] : 'غير متاح'),
            '📊 <b>عدد المجموعة الحالي:</b> ' . ($membership
                ? $membership['count'] . '/' . max(1, (int) $group->capacity) . ' طالب'
                : 'غير متاح'),
            '💰 <b>المبلغ:</b> ' . $this->escape($this->money($subscription->amount, $subscription->currency_code)),
            '💳 <b>طريقة الدفع:</b> ' . $this->escape($this->paymentMethod($subscription->payment_gateway)),
            '✅ <b>حالة الاشتراك:</b> ' . $this->escape($subscription->subscription_status),
            '🕒 <b>وقت التفعيل:</b> ' . $this->escape($subscription->paid_at?->toDateTimeString() ?: now()->toDateTimeString()),
        ];

        if ($transaction) {
            $lines[] = '🧾 <b>رقم العملية:</b> ' . $this->escape($transaction->id);
            $lines[] = '🔗 <b>مرجع الدفع:</b> ' . $this->escape($transaction->gateway_transaction_id ?: 'غير متاح');
        }

        if ($subscription->expired_at) {
            $lines[] = '📅 <b>ينتهي في:</b> ' . $this->escape($subscription->expired_at->toDateTimeString());
        }

        return $this->sendMessage(implode("\n", $lines));
    }

    public function notifyCompletedGroupOnce(CourseGroup $group): bool
    {
        $claimedAt = now();
        $claimed = DB::transaction(function () use ($group, $claimedAt): bool {
            $lockedGroup = CourseGroup::query()->lockForUpdate()->find($group->id);
            if (!$lockedGroup || $lockedGroup->admin_telegram_full_alerted_at) {
                return false;
            }

            $memberCount = $this->activeGroupMemberships($lockedGroup->id)->count();
            if ($memberCount < max(1, (int) $lockedGroup->capacity)) {
                return false;
            }

            $lockedGroup->admin_telegram_full_alerted_at = $claimedAt;
            $lockedGroup->save();
            return true;
        });

        if (!$claimed) {
            return false;
        }

        $group->loadMissing(['course', 'plan']);
        $members = $this->activeGroupMemberships($group->id);
        $users = User::query()
            ->whereIn('id', $members->pluck('user_id'))
            ->get()
            ->keyBy('id');

        $lines = [
            '🏁 <b>اكتملت المجموعة</b>',
            '📚 <b>الكورس:</b> ' . $this->escape($group->course?->title ?: 'غير معروف'),
            '👥 <b>المجموعة:</b> ' . $this->escape($group->name),
            '📦 <b>الباقة:</b> ' . $this->escape($group->plan?->name ?: 'بدون باقة'),
            '📊 <b>العدد:</b> ' . $members->count() . '/' . max(1, (int) $group->capacity),
            '',
            '<b>أعضاء المجموعة:</b>',
        ];

        foreach ($members as $index => $member) {
            $user = $users->get($member->user_id);
            $name = $user?->name ?: trim(($user?->first_name ?? '') . ' ' . ($user?->middle_name ?? '') . ' ' . ($user?->last_name ?? ''));
            $lines[] = ($index + 1) . '. ' . $this->escape($name ?: 'اسم غير مسجل')
                . ' — ' . $this->escape($user?->email ?: 'بريد غير مسجل');
        }

        $messages = $this->splitTelegramLines($lines, 3500);
        $keyboard = ['inline_keyboard' => [[
            ['text' => '▶️ تفعيل المجموعة', 'callback_data' => 'admin:activate:' . $group->id],
        ]]];
        foreach ($messages as $index => $message) {
            $sent = $this->sendMessage(
                $message,
                $index === array_key_last($messages) ? $keyboard : null
            );
            if (!$sent) {
                CourseGroup::whereKey($group->id)
                    ->where('admin_telegram_full_alerted_at', $claimedAt)
                    ->update(['admin_telegram_full_alerted_at' => null]);
                return false;
            }
        }

        return true;
    }

    public function notifyPaymentStatus(Transaction $transaction, string $status, ?string $reason = null): bool
    {
        $transaction->loadMissing(['user', 'course']);
        $payload = $transaction->payload ?? [];
        $isWalletTopup = $transaction->course_id === null || data_get($payload, 'is_wallet_topup', false);
        $statusLabel = $status === 'failed' ? 'فشل الدفع' : 'الدفع قيد التأكيد';

        $lines = [
            ($status === 'failed' ? '❌' : '⏳') . ' <b>' . $this->escape($statusLabel) . '</b>',
            '',
            '👤 <b>الطالب:</b> ' . $this->escape($transaction->user?->name ?: 'غير معروف'),
            '🆔 <b>رقم الطالب:</b> ' . $this->escape($transaction->user_id),
            '📧 <b>البريد:</b> ' . $this->escape($transaction->user?->email ?: 'غير مسجل'),
            '📱 <b>الهاتف:</b> ' . $this->escape($transaction->user?->phone ?: 'غير مسجل'),
            '📌 <b>نوع العملية:</b> ' . ($isWalletTopup ? 'شحن المحفظة' : 'اشتراك كورس'),
        ];

        if (!$isWalletTopup) {
            $lines[] = '📚 <b>الكورس:</b> ' . $this->escape($transaction->course?->title ?: 'غير معروف');
            $planId = data_get($payload, 'plan_id');
            if ($planId) {
                $planName = \App\Models\CoursePlan::where('course_id', $transaction->course_id)->find($planId)?->name;
                $lines[] = '📦 <b>الباقة:</b> ' . $this->escape($planName ?: "رقم {$planId}");
            }
        }

        $lines[] = '💰 <b>المبلغ:</b> ' . $this->escape($this->money($transaction->amount, $transaction->currency_code));
        $lines[] = '💳 <b>طريقة الدفع:</b> ' . $this->escape($this->paymentMethod($transaction->payment_gateway));
        $lines[] = '🧾 <b>رقم العملية:</b> ' . $this->escape($transaction->id);
        $lines[] = '🔗 <b>مرجع الدفع:</b> ' . $this->escape($transaction->gateway_transaction_id ?: 'غير متاح');
        $lines[] = '📍 <b>الحالة:</b> ' . $this->escape($statusLabel);
        $lines[] = '🕒 <b>وقت التحديث:</b> ' . $this->escape(now()->toDateTimeString());

        if ($reason) {
            $lines[] = 'ℹ️ <b>التفاصيل:</b> ' . $this->escape(substr($reason, 0, 500));
        }

        return $this->sendMessage(implode("\n", $lines));
    }

    public function notifyPaymentPendingOnce(Transaction $transaction, ?string $reason = null): bool
    {
        try {
            $claimed = DB::transaction(function () use ($transaction) {
                $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
                if (!$locked || $locked->status !== Transaction::STATUS_PENDING) {
                    return false;
                }

                $payload = $locked->payload ?? [];
                if (!empty($payload['admin_telegram_pending_alerted'])) {
                    return false;
                }

                $payload['admin_telegram_pending_alerted'] = true;
                $locked->payload = $payload;
                $locked->save();
                return true;
            });
        } catch (\Throwable $exception) {
            Log::warning('Admin Telegram pending-payment alert could not be claimed.', [
                'transaction_id' => $transaction->id,
                'exception' => get_class($exception),
            ]);
            return false;
        }

        if (!$claimed) {
            return false;
        }

        $sent = $this->notifyPaymentStatus($transaction->fresh(), 'pending', $reason);
        if (!$sent) {
            try {
                DB::transaction(function () use ($transaction): void {
                    $locked = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
                    if ($locked && $locked->status === Transaction::STATUS_PENDING) {
                        $payload = $locked->payload ?? [];
                        unset($payload['admin_telegram_pending_alerted']);
                        $locked->payload = $payload;
                        $locked->save();
                    }
                });
            } catch (\Throwable $exception) {
                Log::warning('Admin Telegram pending-payment alert could not release its retry marker.', [
                    'transaction_id' => $transaction->id,
                    'exception' => get_class($exception),
                ]);
            }
        }

        return $sent;
    }

    public function notifyWalletMovement(WalletTransaction $walletTransaction): bool
    {
        $walletTransaction->loadMissing('user');
        $type = (string) $walletTransaction->type;
        $credit = in_array($type, ['credit', 'topup', 'course_refund', 'refund'], true);
        $movement = $credit ? 'إضافة رصيد' : 'خصم من المحفظة';
        $amount = (float) $walletTransaction->amount;
        $balanceAfter = (float) $walletTransaction->balance_after;
        $balanceBefore = $credit ? $balanceAfter - $amount : $balanceAfter + $amount;

        $lines = [
            ($credit ? '💵' : '💸') . ' <b>حركة جديدة في محفظة طالب</b>',
            '',
            '👤 <b>الطالب:</b> ' . $this->escape($walletTransaction->user?->name ?: 'غير معروف'),
            '🆔 <b>رقم الطالب:</b> ' . $this->escape($walletTransaction->user_id),
            '📧 <b>البريد:</b> ' . $this->escape($walletTransaction->user?->email ?: 'غير مسجل'),
            '📱 <b>الهاتف:</b> ' . $this->escape($walletTransaction->user?->phone ?: 'غير مسجل'),
            '🔄 <b>نوع الحركة:</b> ' . $this->escape($movement . ' — ' . $this->walletMovementLabel($type)),
            '💰 <b>المبلغ:</b> ' . $this->escape($this->money($amount, 'EGP')),
            '📉 <b>الرصيد قبل:</b> ' . $this->escape($this->money($balanceBefore, 'EGP')),
            '📈 <b>الرصيد بعد:</b> ' . $this->escape($this->money($balanceAfter, 'EGP')),
            '🧾 <b>المرجع:</b> ' . $this->escape($walletTransaction->reference_id ?: 'غير متاح'),
            '📝 <b>البيان:</b> ' . $this->escape($walletTransaction->description ?: 'غير متاح'),
            '🕒 <b>الوقت:</b> ' . $this->escape($walletTransaction->created_at?->toDateTimeString() ?: now()->toDateTimeString()),
        ];

        return $this->sendMessage(implode("\n", $lines));
    }

    public function notifyAttendanceAbsence(
        User $student,
        OnlineLecture $missedLecture,
        OnlineLecture $previousLecture
    ): bool {
        $missedLecture->loadMissing(['course', 'group']);
        $name = $student->name ?: trim(implode(' ', array_filter([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ])));

        $lines = [
            '⚠️ <b>تنبيه غياب عن محاضرة</b>',
            '',
            '👤 <b>الطالب:</b> ' . $this->escape($name ?: 'غير معروف'),
            '📧 <b>البريد:</b> ' . $this->escape($student->email ?: 'غير مسجل'),
            '📚 <b>الكورس:</b> ' . $this->escape($missedLecture->course?->title ?: 'غير معروف'),
            '👥 <b>المجموعة:</b> ' . $this->escape($missedLecture->group?->name ?: 'غير معروفة'),
            '✅ <b>حضر سابقاً:</b> ' . $this->escape($previousLecture->title),
            '❌ <b>غاب عن:</b> ' . $this->escape($missedLecture->title),
            '🕒 <b>موعد المحاضرة:</b> ' . $this->escape($missedLecture->start_date_time?->toDateTimeString() ?: 'غير محدد'),
        ];

        return $this->sendMessage(implode("\n", $lines));
    }

    private function findCompletedTransaction(CourseSubscription $subscription): ?Transaction
    {
        $query = Transaction::where('user_id', $subscription->user_id)
            ->where('course_id', $subscription->course_id)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->orderByDesc('id');

        if ($subscription->plan_id === null) {
            $query->whereNull('payload->plan_id');
        } else {
            $query->where('payload->plan_id', $subscription->plan_id);
        }

        return $query->first();
    }

    private function activeGroupMemberships(int $groupId): Collection
    {
        return DB::table('course_subscriptions')
            ->where('group_id', $groupId)
            ->where(function ($query) {
                $query->whereNull('subscription_status')
                    ->orWhere('subscription_status', CourseSubscription::STATUS_ACTIVE);
            })
            ->groupBy('user_id')
            ->selectRaw('user_id, MIN(id) as first_subscription_id')
            ->orderBy('first_subscription_id')
            ->get();
    }

    private function groupMembershipPosition(int $groupId, int $userId): ?array
    {
        $members = $this->activeGroupMemberships($groupId);
        $position = $members->search(fn ($member) => (int) $member->user_id === $userId);
        if ($position === false) {
            return null;
        }

        return ['position' => $position + 1, 'count' => $members->count()];
    }

    private function splitTelegramLines(array $lines, int $maxLength): array
    {
        $messages = [];
        $current = '';
        foreach ($lines as $line) {
            $candidate = $current === '' ? $line : $current . "\n" . $line;
            if ($current !== '' && mb_strlen($candidate) > $maxLength) {
                $messages[] = $current;
                $current = $line;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $messages[] = $current;
        }

        return $messages;
    }

    private function groupStatusLabel(string $status): string
    {
        return match ($status) {
            'open_for_registration' => 'مفتوحة للتسجيل',
            'waiting_for_students' => 'بانتظار الطلاب',
            'ready_to_start' => 'جاهزة للبدء',
            'active' => 'مفعلة',
            'completed' => 'مكتملة',
            default => $status,
        };
    }

    private function callTelegram(string $method, array $payload): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->connectTimeout(2)
                ->post($this->apiUrl($method), $payload);

            return $response->successful() && $response->json('ok') === true;
        } catch (\Throwable $exception) {
            Log::warning('Admin Telegram API request failed.', [
                'method' => $method,
                'exception' => get_class($exception),
            ]);
            return false;
        }
    }

    private function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot'
            . (string) config('services.admin_telegram.bot_token')
            . '/' . $method;
    }

    private function money(mixed $amount, ?string $currency): string
    {
        return number_format((float) $amount, 2) . ' ' . ($currency ?: 'EGP');
    }

    private function paymentMethod(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'wallet' => 'محفظة الطالب',
            'admin' => 'تفعيل بواسطة الإدارة',
            'free' => 'مجاني',
            '' => 'غير محدد',
            default => (string) $method,
        };
    }

    private function walletMovementLabel(string $type): string
    {
        return match ($type) {
            'credit', 'topup' => 'شحن رصيد',
            'course_purchase' => 'شراء كورس',
            'course_refund', 'refund' => 'استرداد قيمة كورس',
            default => $type,
        };
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
