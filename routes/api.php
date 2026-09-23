<?php

/**
 * ====================================================
 * اسم الملف: api.php
 * المسار: routes/api.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف هو خريطة المسارات المباشرة (API Endpoints) الخاصة بمنصة Code Shell.
 * يربط بين طلبات تطبيق الموبايل Flutter وموقع الويب وبين الـ Controllers المعنية في السيرفر.
 * 
 * هيكل المسارات:
 * 1. Public Routes: مسارات عامة (تسجيل الدخول، إنشاء الحساب، نسيت كلمة المرور، الـ Webhooks).
 * 2. Protected Routes: مسارات محمية بـ auth:sanctum (تعديل البروفايل، الكورسات، الاشتراكات، الحجوزات، الدفع).
 * 3. Teacher Routes: مسارات خاصة بنظام المدرس وإدارة المجموعات واللايفات.
 * 4. Admin Routes: مسارات خاصة بأدمن المنصة وإدارة المحتوى والطلاب.
 * ====================================================
 */

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
/// المسارات دي متاحة لأي حد (Public)، ومش بتحتاج Token أو تسجيل دخول.
/// بتشمل عمليات زي التسجيل، تسجيل الدخول، واستعادة كلمة المرور.

Route::post('/register', [AuthController::class, 'register']);

/// API: POST /api/login
/// الهدف: تسجيل دخول الطالب وإرجاع Token (Sanctum Token).
/// التطبيق بيستخدم الـ Token ده في كل الطلبات الجاية.
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

// Public fallback aliases for teacher zoom link update
Route::match(['get', 'post'], '/teacher/update-zoom-link', [\App\Http\Controllers\Api\TeacherController::class, 'updateZoomLink']);
Route::match(['get', 'post'], '/update-zoom-link', [\App\Http\Controllers\Api\TeacherController::class, 'updateZoomLink']);

// Admin Login Route
/// API: POST /api/admin/login
/// الهدف: تسجيل دخول تطبيق الإدارة. 
/// مفصول عن تسجيل دخول الطالب علشان نقدر نطبق Business Logic أو شروط مختلفة للأدمن.
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// مسار الـ Webhook الخاص ببوت التلجرام
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// Webhook بوابة الدفع: يستقبل إشعارات النجاح/الفشل من البوابة (أو المحاكي)
// بدون مصادقة Sanctum — الحماية عبر التحقق من توقيع الطلب داخل الـ Controller.
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

// EasyKash GET Callback Endpoint (المسار الرسمي لـ Callback عبر HTTP GET)
Route::get('/payments/easykash/callback', [PaymentController::class, 'easykashCallback']);
Route::get('/payment/easykash/callback', [PaymentController::class, 'easykashCallback']);

// مسار فحص التحديثات (متاح لجميع المستخدمين للتأكد من وجود إصدار جديد للتطبيق)
Route::get('/check-version', [AppUpdateController::class, 'checkVersion']);

// مسار تجريبي للتأكد من نجاح تحديث السيرفر
Route::get('/test-deployment', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'التعديلات الجديدة تعمل بنجاح!',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

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
/// المسارات دي محمية بـ auth:sanctum، ومخصصة لتطبيق الإدارة (code_shell_admin).
/// أي تعديل في الروابط دي لازم تسمّع في الـ Services بتاعة تطبيق الأدمن.
/// الجروب ده بيسمح للأدمن يضيف، يعدل، أو يحذف محتوى المنصة.
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
    
    // Groups & Online Lectures
    Route::get('/courses/{courseId}/groups', [\App\Http\Controllers\Api\AdminGroupController::class, 'index']);
    Route::post('/groups', [\App\Http\Controllers\Api\AdminGroupController::class, 'store']);
    Route::put('/groups/{id}', [\App\Http\Controllers\Api\AdminGroupController::class, 'update']);
    Route::post('/groups/{id}/activate', [\App\Http\Controllers\Api\AdminGroupController::class, 'activate']);
    Route::post('/online-lectures', [\App\Http\Controllers\Api\AdminOnlineLectureController::class, 'store']);
    Route::put('/online-lectures/{id}', [\App\Http\Controllers\Api\AdminOnlineLectureController::class, 'update']);
    
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

    // 👨‍🏫 إدارة المدرسين
    Route::get('/teachers', [\App\Http\Controllers\Api\AdminTeacherController::class, 'index']);
    Route::post('/teachers', [\App\Http\Controllers\Api\AdminTeacherController::class, 'store']);
    Route::delete('/teachers/{id}', [\App\Http\Controllers\Api\AdminTeacherController::class, 'destroy']);

    // 📋 طلبات تأجيل المحاضرات
    Route::get('/postponement-requests', [\App\Http\Controllers\Api\AdminTeacherController::class, 'postponementRequests']);
    Route::post('/postponement-requests/{id}/handle', [\App\Http\Controllers\Api\AdminTeacherController::class, 'handlePostponement']);
});

/*
|--------------------------------------------------------------------------
| Public / Student Routes (مسارات المستويات والكورسات العامة)
|--------------------------------------------------------------------------
*/
/// هنا بنجيب تفاصيل المستويات والدروس.
/// ⚠️ مهم: الـ Logic الداخلي (زي ما هنشوف تحت) بيتحكم في مين يشوف إيه، وهل الدرس مقفول ولا مفتوح
/// بناءً على التقدم (Progress) بتاع الطالب وحالة تسجيل الدخوله.

// جلب المجموعات המتاحة لكورس معين
Route::get('/courses/{id}/groups', [CourseController::class, 'getGroups']);

/*
|--------------------------------------------------------------------------
| Protected Routes (تتطلب تسجيل الدخول وتوكن Sanctum)
|--------------------------------------------------------------------------
*/
/// كل الروابط اللي تحت الجروب ده بتطلب إن الـ Request يكون فيه Bearer Token في الـ Header.
/// الـ Token ده بيتم توليده وقت الـ Login عن طريق Sanctum.
/// التطبيق (Flutter) بيبعته مع كل طلب عن طريق Dio Interceptors.
/// لو غيرت طريقة الـ Authentication، الجروب ده كله هيتأثر.

// مسار مؤقت لقراءة أخطاء السيرفر (Logs)
Route::get('/server-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return "No log file found at: $logFile";
    }
    
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    
    return response("<pre style='word-wrap: break-word; white-space: pre-wrap;'>" . htmlspecialchars(implode("", $lastLines)) . "</pre>")
        ->header('Content-Type', 'text/html; charset=UTF-8');
});

Route::middleware('auth:sanctum')->group(function () {

    // 🔒 جلب المستويات، الدروس، والمحاضرات للكورس محمي بالتوكن والاشتراك
    Route::get('/levels/{course_id}', [CourseController::class, 'getLevels']);
    Route::get('/levels/{level_id}/lessons', [CourseController::class, 'getLessonsForLevel']);

    Route::get('/online-lectures/{id}/join', [\App\Http\Controllers\Api\OnlineLectureController::class, 'join']);
    Route::get('/my-lectures', [\App\Http\Controllers\Api\OnlineLectureController::class, 'myLectures']);

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
    Route::get('/courses/{id}/online-lectures', [CourseController::class, 'getCourseOnlineLectures']);

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

    // 👨‍🏫 Teacher Mode Routes (مسارات وضع المدرس في تطبيق الطالب)
    Route::prefix('teacher')->group(function () {
        Route::get('/my-groups', [\App\Http\Controllers\Api\TeacherController::class, 'myGroups']);
        Route::get('/groups/{groupId}/students', [\App\Http\Controllers\Api\TeacherController::class, 'groupStudents']);
        Route::get('/groups/{groupId}/lectures', [\App\Http\Controllers\Api\TeacherController::class, 'groupLectures']);
        Route::get('/my-sessions', [\App\Http\Controllers\Api\TeacherController::class, 'mySessions']);
        Route::get('/sessions/{id}', [\App\Http\Controllers\Api\TeacherController::class, 'sessionDetails']);
        Route::post('/start-lecture', [\App\Http\Controllers\Api\TeacherController::class, 'startLecture']);
        Route::post('/update-zoom-link', [\App\Http\Controllers\Api\TeacherController::class, 'updateZoomLink']);
        Route::post('/end-lecture', [\App\Http\Controllers\Api\TeacherController::class, 'endLecture']);
        Route::post('/postpone', [\App\Http\Controllers\Api\TeacherController::class, 'requestPostponement']);
        Route::post('/break', [\App\Http\Controllers\Api\TeacherController::class, 'startBreak']);
        Route::post('/end-break', [\App\Http\Controllers\Api\TeacherController::class, 'endBreak']);
    });
});