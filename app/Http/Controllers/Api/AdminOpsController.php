<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ====================================================
 * AdminOpsController — عمليات الأدمن على حسابات الطلاب
 * 1. lookup: البحث عن طالب بالبريد الإلكتروني (الاسم + الهاتف + رصيد المحفظة)
 * 2. creditWallet: شحن رصيد المحفظة مباشرة
 * 3. subscribeStudent: اشتراك طالب في كورس مباشرة — محمي بكلمة سر العمليات
 * 4. index: سجل العمليات (شحن / اشتراكات)
 *
 * 🔐 كلمة سر العمليات تُخزن سيرفرياً فقط (config/services.php → admin_ops.password)
 * ولا تُرسل أبداً للمتصفح — العمليات الحساسة ترفض بدونها.
 * ====================================================
 */
class AdminOpsController extends Controller
{
    /** التحقق من كلمة سر العمليات الحساسة */
    private function ensureOperationPassword(Request $request): ?JsonResponse
    {
        $expected = (string) config('services.admin_ops.password', '');
        $given = (string) $request->input('admin_password', '');

        if ($expected === '' || !hash_equals($expected, $given)) {
            return response()->json([
                'status'  => false,
                'message' => 'كلمة سر العمليات غير صحيحة. لا يمكن تنفيذ هذه العملية.',
            ], 403);
        }
        return null;
    }

    /**
     * البحث عن طالب بالبريد الإلكتروني وإرجاع بياناته ورصيد محفظته
     * POST /api/admin/users/lookup  { email }
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', trim($request->input('email')))->first();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'لا يوجد طالب مسجل بهذا البريد الإلكتروني.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => [
                'id'             => $user->id,
                'name'           => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? 'طالب'),
                'email'          => $user->email,
                'phone'          => $user->phone ?? 'غير مسجل',
                'wallet_balance' => (float) ($user->wallet_balance ?? 0),
            ],
        ]);
    }

    /**
     * شحن رصيد محفظة طالب مباشرة (محمي بجلسة الأدمن)
     * POST /api/admin/wallet/admin-credit  { user_id, amount, notes? }
     */
    public function creditWallet(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount'  => 'required|numeric|min:1',
            'notes'   => 'nullable|string|max:500',
        ]);

        $amount = round((float) $request->input('amount'), 2);

        $result = DB::transaction(function () use ($request, $amount) {
            $user = User::lockForUpdate()->find($request->input('user_id'));
            if (!$user) return null;

            $user->wallet_balance = (float) ($user->wallet_balance ?? 0) + $amount;
            $user->save();

            \App\Models\WalletTransaction::create([
                'user_id'       => $user->id,
                'type'          => 'credit',
                'amount'        => $amount,
                'balance_after' => (float) $user->wallet_balance,
                'reference_id'  => 'admin-' . (auth('sanctum')->id() ?? '0'),
                'description'   => 'شحن رصيد بواسطة الأدمن' . ($request->filled('notes') ? ' — ' . $request->input('notes') : ''),
            ]);

            return $user;
        });

        if (!$result) {
            return response()->json(['status' => false, 'message' => 'الطالب غير موجود.'], 404);
        }

        // تسجيل العملية في سجل الأدمن
        DB::table('admin_operations')->insert([
            'operation'  => 'topup',
            'admin_id'   => auth('sanctum')->id(),
            'user_id'    => $result->id,
            'user_email' => $result->email,
            'amount'     => $amount,
            'notes'      => $request->input('notes'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Admin credited wallet: User #{$result->id} +{$amount} EGP");

        return response()->json([
            'status'         => true,
            'message'        => "تم شحن {$amount} ج.م إلى محفظة الطالب بنجاح!",
            'wallet_balance' => (float) $result->wallet_balance,
        ]);
    }

    /**
     * اشتراك طالب في كورس مباشرة بواسطة الأدمن (بدون دفع إلكتروني)
     * POST /api/admin/subscriptions/admin-subscribe  { user_id, course_id, admin_password }
     */
    public function subscribeStudent(Request $request): JsonResponse
    {
        $gate = $this->ensureOperationPassword($request);
        if ($gate) return $gate;

        $request->validate([
            'user_id'   => 'required|exists:users,id',
            'course_id' => 'required',
        ]);

        $student = User::find($request->input('user_id'));
        $course = Course::findCourseSafely($request->input('course_id'));

        if (!$student || !$course) {
            return response()->json(['status' => false, 'message' => 'الطالب أو الكورس غير موجود.'], 404);
        }

        // منع الاشتراك المزدوج
        $already = DB::table('course_subscriptions')
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->where(function ($q) {
                $q->whereNull('subscription_status')->orWhere('subscription_status', 'active');
            })
            ->exists();

        if ($already) {
            return response()->json([
                'status'  => false,
                'message' => "الطالب {$student->name} مشترك بالفعل في هذا الكورس.",
            ], 422);
        }

        $amount = (float) ($course->price ?? 0);

        // معاملة مكتملة بواسطة الأدمن ثم تفعيل الاشتراك عبر نفس المسار الرسمي
        // (يشمل تعيين المجموعة المفتوحة وفق القواعد الصارمة)
        $transaction = Transaction::create([
            'user_id'         => $student->id,
            'course_id'       => $course->id,
            'amount'          => $amount,
            'currency_code'   => 'EGP',
            'payment_gateway' => 'admin',
            'status'          => Transaction::STATUS_COMPLETED,
            'paid_at'         => now(),
            'payload'         => ['subscribed_by_admin' => true],
        ]);

        app(\App\Services\SubscriptionService::class)->activateFromTransaction($transaction, [
            'subscribed_by_admin' => true,
        ]);

        // تسجيل العملية في سجل الأدمن
        DB::table('admin_operations')->insert([
            'operation'  => 'subscribe',
            'admin_id'   => auth('sanctum')->id(),
            'user_id'    => $student->id,
            'user_email' => $student->email,
            'course_id'  => $course->id,
            'amount'     => $amount,
            'notes'      => 'اشتراك مباشر بواسطة الأدمن',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info("Admin subscribed student: User #{$student->id} → Course #{$course->id}");

        return response()->json([
            'status'  => true,
            'message' => "تم اشتراك الطالب \"{$student->name}\" في كورس \"{$course->title}\" بنجاح!",
        ]);
    }

    /**
     * سجل عمليات الأدمن (شحن / اشتراكات)
     * GET /api/admin/operations?type=topup|subscribe
     */
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('admin_operations')->orderBy('created_at', 'desc')->limit(100);

        if ($request->query('type') && in_array($request->query('type'), ['topup', 'subscribe'])) {
            $query->where('operation', $request->query('type'));
        }

        $operations = $query->get()->map(function ($op) {
            $user = $op->user_id ? User::find($op->user_id) : null;
            $course = $op->course_id ? Course::find($op->course_id) : null;
            $op->user_name = $user ? (trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name) : '—';
            $op->course_title = $course ? $course->title : null;
            return $op;
        });

        return response()->json([
            'status' => true,
            'data'   => $operations,
        ]);
    }
}
