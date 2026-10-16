<?php

namespace App\Observers;

use App\Models\WalletTransaction;
use App\Services\AdminTelegramBotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WalletTransactionObserver
{
    public function created(WalletTransaction $walletTransaction): void
    {
        $walletTransactionId = $walletTransaction->id;
        DB::afterCommit(function () use ($walletTransactionId): void {
            try {
                $fresh = WalletTransaction::find($walletTransactionId);
                if ($fresh) {
                    app(AdminTelegramBotService::class)->notifyWalletMovement($fresh);
                }
            } catch (Throwable $exception) {
                Log::warning('Admin Telegram wallet alert could not be prepared.', [
                    'wallet_transaction_id' => $walletTransactionId,
                    'exception' => get_class($exception),
                ]);
            }
        });
    }
}
