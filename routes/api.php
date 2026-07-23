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
| Public Routes (المسارات العامة)
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// مسار الـ Webhook الخاص ببوت التلجرام (تم تعديل اسم الدالة إلى handle)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Admin Routes (إضافة لغات، كورسات، مستويات، دروس بالفيديو، والمستخدمين)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/categories', [AdminContentController::class, 'storeCategory']);
    Route::post('/courses', [AdminContentController::class, 'storeCourse']);
    Route::post('/levels', [AdminContentController::class, 'storeLevel']);
    Route::post('/lessons', [AdminContentController::class, 'storeLessonWithQuiz']);
    Route::get('/users', [AdminContentController::class, 'getUsers']);
});

/*
|--------------------------------------------------------------------------
| Public / Student Routes (مسارات المستويات والكورسات العامة)
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
| Protected Routes (تتطلب تسجيل الدخول وتوكن Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

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

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);

    // Course Waitlist / Reservations
    Route::get('/courses/{id}/reservation-status', [CourseReservationController::class, 'getStatus']);
    Route::post('/courses/{id}/reserve', [CourseReservationController::class, 'toggleReservation']);
});