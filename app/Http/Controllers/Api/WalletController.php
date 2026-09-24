<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    /**
     * جلب رصيد المحفظة الحالي وسجل المعاملات
     */
    public function getBalance(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'status'          => true,
            'wallet_balance'  => (float) ($user->wallet_balance ?? 0.00),
            'currency_code'   => 'EGP',
            'currency_symbol' => 'ج.م',
            'transactions'    => $transactions,
        ]);
    }

    /**
     * بدء عملية تعبئة المحفظة (Top-up) عبر EasyKash
     */
    public function topup(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'amount' => 'required|numeric|min:30',
        ], [
            'amount.min' => 'يجب إدخال مبلغ يساوي 30 جنيه أو أكبر.',
            'amount.required' => 'يرجى إدخال مبلغ التعبئة المطلوب.',
        ]);

        $amount = (float) $request->input('amount');

        $gateway = PaymentGatewayManager::active();

        // 1. إنشاء معاملة مالية جديدة لتعبئة المحفظة
        $transaction = new Transaction([
            'user_id'         => $user->id,
            'course_id'       => null,
            'amount'          => $amount,
            'currency_code'   => 'EGP',
            'payment_gateway' => $gateway->name(),
            'status'          => Transaction::STATUS_PENDING,
            'payload'         => [
                'is_wallet_topup' => true,
                'description'     => "تعبئة محفظة كود شيل بمبلغ {$amount} ج.م",
            ],
        ]);
        $transaction->save();

        try {
            $returnUrl = (string) config('payment.easykash.callback_url', config('payment.return_url', 'https://code-shell-server-production.up.railway.app/api/payments/easykash/callback'));
            $created = $gateway->createPayment($transaction, $returnUrl);
        } catch (\Throwable $e) {
            $transaction->update([
                'status'  => Transaction::STATUS_FAILED,
                'payload' => array_merge($transaction->payload ?? [], ['error' => $e->getMessage()]),
            ]);
            throw $e;
        }

        $transaction->update([
            'gateway_transaction_id' => $created['gateway_transaction_id'],
            'payload'                => array_merge($transaction->payload ?? [], $created['payload'] ?? []),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تم تجهيز جلسة الدفع لتعبئة المحفظة بنجاح.',
            'data'    => [
                'transaction_id'  => $transaction->id,
                'payment_url'     => $created['payment_url'],
                'amount'          => (float) $transaction->amount,
                'currency_code'   => 'EGP',
                'currency_symbol' => 'ج.م',
            ],
        ]);
    }
}
