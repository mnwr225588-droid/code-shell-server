<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\AdminTelegramBotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionObserver
{
    public function updated(Transaction $transaction): void
    {
        if (!$transaction->wasChanged('status') || $transaction->status !== Transaction::STATUS_FAILED) {
            return;
        }

        $transactionId = $transaction->id;
        DB::afterCommit(function () use ($transactionId): void {
            try {
                $fresh = Transaction::find($transactionId);
                if ($fresh && $fresh->status === Transaction::STATUS_FAILED) {
                    $reason = data_get($fresh->payload, 'failure_reason');
                    if (!$reason && data_get($fresh->payload, 'error')) {
                        $reason = 'تعذر إنشاء جلسة الدفع لدى مزود الخدمة.';
                    }

                    app(AdminTelegramBotService::class)->notifyPaymentStatus(
                        $fresh,
                        'failed',
                        $reason
                    );
                }
            } catch (Throwable $exception) {
                Log::warning('Admin Telegram failed-payment alert could not be prepared.', [
                    'transaction_id' => $transactionId,
                    'exception' => get_class($exception),
                ]);
            }
        });
    }
}
