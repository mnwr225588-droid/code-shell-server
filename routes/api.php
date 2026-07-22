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
use App\Models\Level;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Telegram Bot Webhook Route
|--------------------------------------------------------------------------
*/

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Telegram Authentication Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth/telegram')->group(function () {
    Route::post('/link', [TelegramAuthController::class, 'linkTelegram']);
    Route::post('/send-otp', [TelegramAuthController::class, 'sendOtp']);
    Route::post('/reset-password', [TelegramAuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (إضافة المستويات والدروس والاختبارات)
|--------------------------------------------------------------------------
*/

Route::post('/admin/levels', [AdminContentController::class, 'storeLevel']);
Route::post('/admin/lessons', [AdminContentController::class, 'storeLessonWithQuiz']);

/*
|--------------------------------------------------------------------------
| Course Content & Levels Route (جلب البيانات مرتبة للتطبيق)
|--------------------------------------------------------------------------
*/

Route::get('/levels/{course_id}', function ($course_id) {
    return response()->json([
        'status' => true,
        'data'   => Level::where('course_id', $course_id)
            ->orderBy('order_num', 'asc')
            ->with(['lessons.questions.options'])
            ->get()
    ]);
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    Route::get('/categories', [CategoryController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Courses
    |--------------------------------------------------------------------------
    */

    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);

    /*
    |--------------------------------------------------------------------------
    | Progress
    |--------------------------------------------------------------------------
    */

    Route::get('/progress', [ProgressController::class, 'index']);
    Route::post('/progress', [ProgressController::class, 'save']);

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [NotificationController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Reservations & Waitlist
    |--------------------------------------------------------------------------
    */

    Route::post('/reserve-course', [ReservationController::class, 'store']);
    
    // مسارات قائمة انتظار الكورسات القادمة
    Route::get('/courses/{id}/reservation-status', [CourseReservationController::class, 'getStatus']);
    Route::post('/courses/{id}/reserve', [CourseReservationController::class, 'toggleReservation']);

});