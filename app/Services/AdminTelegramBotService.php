<?php

namespace App\Services;

use App\Models\CourseSubscription;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminTelegramBotService
{
    public function sendMessage(string $message): bool
    {
        $token = (string) config('services.admin_telegram.bot_token');
        $chatId = (string) config('services.admin_telegram.chat_id');

        if (
            $token === ''
            || $chatId === ''
            || !preg_match('/^\d+$/', $chatId)
            || hash_equals((string) config('services.telegram.bot_token'), $token)
        ) {
            Log::warning('Admin Telegram alert was not sent because its separate bot token or private chat ID is missing or invalid.');
            return false;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->connectTimeout(2)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

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
