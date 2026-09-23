<?php

/**
 * ====================================================
 * اسم الملف: EnsureUserIsSubscribedToCourse.php
 * المسار: app/Http/Middleware/EnsureUserIsSubscribedToCourse.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الميدلوير (Middleware) هو طبقة الحماية والتأكد من الصلاحيات (Authorization Layer) الخاصة بمحتوى الكورسات.
 * يضمن عدم تمكن أي طالب من الوصول للمستويات أو الدروس أو الفيديوات أو المحاضرات إلا إذا كان يملك اشتراكاً فعالاً ومؤكداً
 * أو كان حسابه أدمن، ويعيد استجابة HTTP 403 Forbidden في حال عدم توفر الاشتراك.
 * 
 * الاستخدام:
 * يتم تطبيقه على المسارات المحمية في routes/api.php أو استدعاؤه داخل Controllers.
 * ====================================================
 */

namespace App\Http\Middleware;

use App\Models\Course;
use App\Models\Level;
use App\Models\OnlineLecture;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSubscribedToCourse
{
    /**
     * اعتراض الطلب وتفقد وجود اشتراك فعال قبل السماح بالمرور
     *
     * @param Request $request طلب الـ HTTP القادم من التطبيق أو المتصفح
     * @param Closure $next الخطوة التالية في الـ Request Pipeline
     * @return Response استجابة HTTP (200 أو 403 أو 401)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user() ?: $request->user();

        // 1. التحقق من تسجيل الدخول
        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'يجب تسجيل الدخول أولاً للوصول للمحتوى.',
            ], 401);
        }

        // 2. الأدمن يملك وصولاً كاملاً دون الحاجة لاشتراك
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        // 3. استخراج معرف الكورس (course_id) من البارامترات المتاحة في الطلب
        $courseId = $request->route('course') 
            ?: $request->route('course_id') 
            ?: $request->route('id') 
            ?: $request->input('course_id');

        // إذا كان البارامتر يمثل level_id
        if (!$courseId && $request->route('level_id')) {
            $level = Level::find($request->route('level_id'));
            $courseId = $level?->course_id;
        }

        // إذا كان البارامتر يمثل محاضرة online_lecture
        if (!$courseId && $request->route('lecture_id')) {
            $lecture = OnlineLecture::find($request->route('lecture_id'));
            $courseId = $lecture?->course_id;
        }

        if (!$courseId) {
            return $next($request);
        }

        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'status'  => false,
                'message' => 'الكورس غير موجود.',
            ], 404);
        }

        // 4. التحقق من أن المستخدم يملك اشتراكاً نشطاً (subscription_status = 'active' أو null قديم)
        if (!$course->isUserSubscribed($user->id)) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، يجب وجود اشتراك مؤكد وفعال للوصول إلى هذا المحتوى.',
            ], 403);
        }

        return $next($request);
    }
}
