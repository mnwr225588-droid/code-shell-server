<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AdminContentController;
use App\Http\Controllers\Api\TelegramAuthController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\CourseReservationController;
use App\Http\Controllers\Api\AppUpdateController;
use App\Http\Controllers\Api\AppReviewController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\AdminAuthController;
use App\Models\Level;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Public Routes (المسارات العامة)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

// Admin Login Route
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// مسار الـ Webhook الخاص ببوت التلجرام
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// Webhook بوابة الدفع: يستقبل إشعارات النجاح/الفشل من البوابة (أو المحاكي)
// بدون مصادقة Sanctum — الحماية عبر التحقق من توقيع الطلب داخل الـ Controller.
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// مسار فحص التحديثات (متاح لجميع المستخدمين للتأكد من وجود إصدار جديد للتطبيق)
Route::get('/check-version', [AppUpdateController::class, 'checkVersion']);

// App info for the public website
Route::get('/app-info', [AppUpdateController::class, 'appInfo']);

// Download app file with real download counter
Route::get('/download/{platform}', [AppUpdateController::class, 'download']);

// حالة تفعيل التنزيل لكل منصة (يستخدمها الموقع وتطبيق الأدمن)
Route::get('/download-settings', [AppUpdateController::class, 'downloadSettings']);

// تقييمات وتعليقات التطبيق (يستخدمها موقع E:\code_shell_web)
Route::get('/app-reviews', [AppReviewController::class, 'index']);
Route::post('/app-reviews', [AppReviewController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Server & Database Diagnostic / Fix Route (مسار الفحص الشامل وإصلاح الأدمن)
|--------------------------------------------------------------------------
*/
Route::get('/server-check', function () {
    try {
        $user = User::where('email', 'admin@codeshell.com')->first();
        
        if (!$user) {
            $user = new User();
            $user->email = 'admin@codeshell.com';
        }
        
        // تعبئة كافة الحقول المحتملة لتجنب أي قيود Not Null
        $user->name = 'Admin';
        
        if (Schema::hasColumn('users', 'first_name')) {
            $user->first_name = 'Admin';
        }
        if (Schema::hasColumn('users', 'middle_name')) {
            $user->middle_name = 'Admin';
        }
        if (Schema::hasColumn('users', 'last_name')) {
            $user->last_name = 'System';
        }
        if (Schema::hasColumn('users', 'username')) {
            $user->username = 'admin';
        }
        if (Schema::hasColumn('users', 'phone')) {
            $user->phone = '0123456789';
        }
        
        $user->password = Hash::make('password');
        
        if (Schema::hasColumn('users', 'role')) {
            $user->role = 'admin';
        }
        
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'تم إنشاء حساب الأدمن وتجاوز القيود بنجاح!',
            'admin_user' => $user
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'line' => $e->getLine()
        ], 500);
    }
});

Route::get('/create-admin-fix', function () {
    return redirect('/api/server-check');
});

/*
|--------------------------------------------------------------------------
| Admin Routes (إضافة لغات، كورسات، مستويات، دروس بالفيديو، والمستخدمين)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/courses', [AdminContentController::class, 'getCourses']);
    Route::post('/categories', [AdminContentController::class, 'storeCategory']);
    Route::post('/courses', [AdminContentController::class, 'storeCourse']);
    Route::put('/courses/{id}', [AdminContentController::class, 'updateCourse']);
    Route::post('/levels', [AdminContentController::class, 'storeLevel']);
    Route::post('/lessons', [AdminContentController::class, 'storeLessonWithQuiz']);
    Route::get('/users', [AdminContentController::class, 'getUsers']);
    Route::get('/users/{id}', [AdminContentController::class, 'showUser']);
    Route::post('/courses/{id}/toggle-publish', [AdminContentController::class, 'togglePublish']);
    
    Route::get('/reservations', [\App\Http\Controllers\Api\AdminReservationController::class, 'getCoursesWithReservationCounts']);
    Route::get('/reservations/{course_id}', [\App\Http\Controllers\Api\AdminReservationController::class, 'getCourseReservations']);

    Route::get('/subscriptions', [\App\Http\Controllers\Api\AdminSubscriptionController::class, 'getCoursesWithSubscriptionCounts']);
    Route::get('/subscriptions/{course_id}', [\App\Http\Controllers\Api\AdminSubscriptionController::class, 'getCourseSubscriptions']);
    
    // مسار رفع تحديثات التطبيق من لوحة الأدمن
    Route::post('/upload-version', [AppUpdateController::class, 'uploadVersion']);
    Route::post('/upload-release-background', [AppUpdateController::class, 'uploadReleaseBackground']);
    // الرفع المجزأ لملفات التطبيق الكبيرة (مقاطع + تجميع)
    Route::post('/upload-chunk', [AppUpdateController::class, 'uploadChunk']);
    Route::post('/upload-complete', [AppUpdateController::class, 'completeChunkedUpload']);
    // مسارات إدارة التحديثات: قائمة التحديثات + حذف تحديث
    Route::get('/releases', [AppUpdateController::class, 'getUpdates']);
    Route::delete('/releases/{id}', [AppUpdateController::class, 'deleteUpdate']);
    // التحكم في تفعيل/إيقاف التنزيل من الموقع لكل منصة
    Route::put('/download-settings', [AppUpdateController::class, 'updateDownloadSettings']);
    
    // Delete operations
    Route::delete('/levels/{id}', [AdminContentController::class, 'deleteLevel']);
    Route::delete('/lessons/{id}', [AdminContentController::class, 'deleteLesson']);
    Route::delete('/users/{id}', [AdminContentController::class, 'deleteUser']);
    
    // Dashboard Stats
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index']);

    // 📢 إرسال إشعارات من لوحة الأدمن (كل المستخدمين / مشتركو كورس / غير المشتركين / فردي بالإيميل) + صورة اختيارية
    Route::post('/send-notification', [NotificationController::class, 'send']);
    // 📋 سجل الإشعارات المرسلة سابقاً (لتطبيق الأدمن)
    Route::get('/notifications-history', [NotificationController::class, 'history']);
});

/*
|--------------------------------------------------------------------------
| Public / Student Routes (مسارات المستويات والكورسات العامة)
|--------------------------------------------------------------------------
*/
// جلب الدروس الخاصة بمستوى معين
Route::get('/levels/{id}/lessons', function ($id) {
    $level = \App\Models\Level::with(['lessons' => function($q) {
        $q->orderBy('order_num', 'asc')->with('questions.options');
    }])->findOrFail($id);

    return response()->json([
        'status' => true,
        'data'   => $level->lessons
    ]);
});

Route::get('/levels/{course_id}', function ($course_id) {
    $levels = Level::where('course_id', $course_id)
        ->orderBy('order_num', 'asc')
        ->with(['lessons' => function($q) {
            $q->orderBy('order_num', 'asc')->with('questions.options');
        }])
        ->get();

    $userId = auth('sanctum')->id();
    $completedLessonIds = [];
    $isAdmin = auth('sanctum')->user()?->isAdmin() ?? false;
    
    if ($userId) {
        $completedLessonIds = \App\Models\LessonCompletion::where('user_id', $userId)
            ->pluck('lesson_id')
            ->toArray();
    }

    $isNextUnlocked = true; // First lesson is always unlocked

    foreach ($levels as $level) {
        $levelLocked = true;
        foreach ($level->lessons as $lesson) {
            // حساب الأدمن: كل الدروس مفتوحة دائماً دون قيود.
            $lesson->is_locked = $isAdmin ? false : !$isNextUnlocked;
            
            if (!$lesson->is_locked) {
                $levelLocked = false;
            }

            // Check if this lesson is completed, which unlocks the next one
            if (in_array($lesson->id, $completedLessonIds)) {
                $lesson->is_completed = true;
                $isNextUnlocked = true;
            } else {
                $lesson->is_completed = false;
                if (!$lesson->is_optional) {
                    $isNextUnlocked = false;
                } else {
                    $isNextUnlocked = true;
                }
            }
        }
        
        // If the entire level is optional, it should not block the next level
        if ($level->is_optional) {
            $isNextUnlocked = true;
        }
        
        $level->is_locked = $isAdmin ? false : $levelLocked;
    }

    return response()->json([
        'status' => true,
        'data'   => $levels
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (تتطلب تسجيل الدخول وتوكن Sanctum)
|--------------------------------------------------------------------------
*/
// مسار مؤقت لقراءة أخطاء السيرفر (Logs)
Route::get('/server-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return "No log file found at: $logFile";
    }
    
    // قراءة آخر 100 سطر لتجنب انهيار المتصفح
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    
    return response("<pre style='word-wrap: break-word; white-space: pre-wrap;'>" . htmlspecialchars(implode("", $lastLines)) . "</pre>")
        ->header('Content-Type', 'text/html; charset=UTF-8');
});

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']); // مسار تغيير كلمة المرور
    Route::post('/delete-account', [AuthController::class, 'deleteAccount']); // مسار حذف الحساب نهائياً
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']); // مسار إعادة إرسال بريد التفعيل

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // Categories & Courses
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);

    // Progress
    Route::get('/progress', [ProgressController::class, 'index']);
    Route::post('/progress', [ProgressController::class, 'save']);
    Route::post('/progress/lesson/{lesson_id}/complete', [ProgressController::class, 'markLessonComplete']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // FCM Token (اشعارات فايربيس) — يستقبله تطبيق الطالب بعد تسجيل الدخول
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);

    // Course Waitlist / Reservations
    Route::get('/courses/{id}/reservation-status', [CourseReservationController::class, 'getStatus']);
    Route::post('/courses/{id}/reserve', [CourseReservationController::class, 'toggleReservation']);

    // Course Subscriptions
    Route::get('/courses/{id}/subscription-status', [\App\Http\Controllers\Api\CourseSubscriptionController::class, 'getStatus']);
    Route::post('/courses/{id}/subscribe', [\App\Http\Controllers\Api\CourseSubscriptionController::class, 'subscribe']);
    Route::post('/courses/{id}/cancel', [\App\Http\Controllers\Api\CourseSubscriptionController::class, 'cancel']);

    // 💳 Payment Gateway (نظام الدفع الإلكتروني)
    Route::post('/courses/{id}/pay', [PaymentController::class, 'initiate']);
    Route::get('/courses/{id}/payment-status', [PaymentController::class, 'paymentStatus']);

    // 🌐 Telegram Integration Routes (مهمة لربط التطبيق بالبوت)
    Route::get('/telegram/bind-url', [TelegramWebhookController::class, 'getBindUrl']);
    Route::get('/telegram/verify-url', [TelegramWebhookController::class, 'getBindUrl']);
    Route::get('/telegram/otp-url', [TelegramWebhookController::class, 'getOtpUrl']);
});