<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseUser;
use App\Models\Transaction;
use App\Services\EasyKashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EasyKashController extends Controller
{
    protected EasyKashService $easyKashService;

    public function __construct(EasyKashService $easyKashService)
    {
        $this->easyKashService = $easyKashService;
    }

    /**
     * استقبال طلب العودة/الـ Callback من بوابة EasyKash وتسجيل اشتراك الطالب
     */
    public function handleCallback(Request $request)
    {
        $data = $request->all();
        Log::info('EasyKash Callback Payload Received:', $data);

        // رابط العودة إلى واجهة المستخدم الفرونت إند
        $frontendUrl = env('FRONTEND_COURSES_URL', 'https://codeshell.kesug.com/courses.html');

        // 1. استخراج مرجع الطلب (مرسل بـ customerReference أو merchant_order_id)
        $orderRef = $request->input('customerReference') 
                 ?? $request->input('merchant_order_id') 
                 ?? $request->input('order_id');

        $transactionId = null;
        if ($orderRef && preg_match('/CS-TX-(\d+)-/', $orderRef, $matches)) {
            $transactionId = (int) $matches[1];
        }

        // البحث عن المعاملة في قاعدة البيانات
        $transaction = null;
        if ($transactionId) {
            $transaction = Transaction::find($transactionId);
        }

        if (!$transaction && $orderRef) {
            $transaction = Transaction::where('gateway_transaction_id', $orderRef)->first();
        }

        if (!$transaction) {
            Log::error('EasyKash Callback: Transaction not found for reference ' . ($orderRef ?? 'N/A'));
            return redirect($frontendUrl . '?status=failed&message=' . urlencode('المعاملة غير مسجلة بالنظام'));
        }

        // 2. التحقق من صحة التوقيع الرقمي HMAC (اختياري/مرن مع الاستعلام السيرفري)
        $signature = $request->input('signature') ?? $request->input('hmac');
        $isValidSignature = $this->easyKashService->validateSignature($data, $signature);

        if (!$isValidSignature) {
            Log::warning('EasyKash Callback: Invalid HMAC Signature for Transaction ID #' . $transaction->id);
        }

        // 3. التحقق من حالة الدفع القادمة من EasyKash
        $rawStatus = strtoupper((string) ($request->input('status') ?? $request->input('payment_status') ?? ''));

        // في حالة عدم وضوح الحالة أو الشك، يتم الاستعلام المباشر من API بوابة EasyKash
        $isPaid = in_array($rawStatus, ['PAID', 'SUCCESS', 'COMPLETED', 'SUCCESSFUL']);
        
        if (!$isPaid && $orderRef) {
            $verification = $this->easyKashService->verifyTransaction($orderRef);
            if (($verification['status'] ?? '') === 'completed') {
                $isPaid = true;
            }
        }

        // 4. معالجة حالة الدفع الناجحة
        if ($isPaid) {
            DB::beginTransaction();
            try {
                // تحديث حالة المعاملة المالية
                $transaction->update([
                    'status'                 => 'completed',
                    'paid_at'                => now(),
                    'gateway_transaction_id' => $request->input('providerRefNum') ?? $orderRef ?? $transaction->gateway_transaction_id,
                ]);

                // تفعيل اشتراك الطالب في الكورس
                if ($transaction->user_id && $transaction->course_id) {
                    CourseUser::updateOrCreate(
                        [
                            'user_id'   => $transaction->user_id,
                            'course_id' => $transaction->course_id,
                        ],
                        [
                            'status'        => 'active',
                            'subscribed_at' => now(),
                            'updated_at'    => now(),
                        ]
                    );
                }

                DB::commit();

                Log::info('EasyKash Payment Completed Successfully for Transaction #' . $transaction->id);

                // إعادة توجيه الطالب إلى الموقع للبدء بمشاهدة الكورس
                return redirect($frontendUrl . '?status=success&course_id=' . $transaction->course_id . '&tx=' . $transaction->id);

            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('EasyKash Callback Fulfillment Error: ' . $e->getMessage());
                return redirect($frontendUrl . '?status=failed&message=' . urlencode('حدث خطأ أثناء تفعيل الاشتراك'));
            }
        }

        // 5. في حالة فشل أو إلغاء عملية الدفع
        $transaction->update(['status' => 'failed']);
        Log::warning('EasyKash Payment Failed/Cancelled for Transaction #' . $transaction->id);

        return redirect($frontendUrl . '?status=failed&message=' . urlencode('لم تم عملية الدفع بنجاح'));
    }
}