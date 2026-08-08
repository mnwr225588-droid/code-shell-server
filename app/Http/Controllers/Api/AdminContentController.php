<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AdminContentController extends Controller
{
    // 0️⃣ جلب جميع الكورسات (بما فيها غير المنشورة)
    public function getCourses(Request $request)
    {
        // Admin needs to see all courses to manage them
        $courses = Course::with('category')->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => true,
            'data'   => $courses
        ]);
    }

    // 1️⃣ إضافة/تحديث قسم أو لغة برمجة مع الأيقونة
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $iconPath = null;
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('categories', 'public');
        }

        $category = Category::create([
            'name' => $request->name,
            'icon' => $iconPath,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تمت إضافة القسم بنجاح',
            'data'    => $category
        ]);
    }

    // 2️⃣ إضافة كورس مع الغلاف والنوع (مجاني/مدفوع/قريباً)
    public function storeCourse(Request $request)
    {
        $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'thumbnail'      => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_free'        => 'required|boolean',
            'price'          => 'nullable|numeric',
            'is_coming_soon' => 'boolean',
            'prices'         => 'nullable',
            'prices.*'       => 'nullable|numeric',
        ]);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $prices = PricingService::normalizePrices($request->input('prices'));

        $course = Course::create([
            'category_id'    => $request->category_id,
            'title'          => $request->title,
            'description'    => $request->description,
            'thumbnail'      => $thumbnailPath,
            'is_free'        => $request->is_free,
            // العمود القديم للتوافق؛ المصدر الحقيقي هو مصفوفة prices (EGP افتراضياً).
            'price'          => $request->is_free ? 0 : ($request->price ?? $prices['EGP'] ?? 0),
            'prices'         => $prices,
            'is_coming_soon' => $request->is_coming_soon ?? false,
            'is_active'      => true,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'تمت إضافة الكورس بنجاح',
            'data'    => $course
        ]);
    }

    // 2️⃣.ب تعديل كورس (البيانات الأساسية + الأسعار متعددة العملات)
    public function updateCourse(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $request->validate([
            'category_id'    => 'sometimes|exists:categories,id',
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'is_free'        => 'sometimes|boolean',
            'is_active'      => 'sometimes|boolean',
            'is_coming_soon' => 'sometimes|boolean',
            'prices'         => 'nullable',
            'prices.*'       => 'nullable|numeric',
        ]);

        if ($request->has('category_id')) {
            $course->category_id = $request->category_id;
        }
        if ($request->has('title')) {
            $course->title = $request->title;
        }
        if ($request->has('description')) {
            $course->description = $request->description;
        }
        if ($request->has('is_free')) {
            $course->is_free = $request->is_free;
        }
        if ($request->has('is_active')) {
            $course->is_active = $request->is_active;
        }
        if ($request->has('is_coming_soon')) {
            $course->is_coming_soon = $request->is_coming_soon;
        }
        if ($request->has('prices')) {
            $course->prices = PricingService::normalizePrices($request->input('prices'));
        }

        $course->save();

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث الكورس بنجاح',
            'data'    => $course
        ]);
    }

    // 3️⃣ إضافة مستوى داخل كورس
    public function storeLevel(Request $request)
    {
        $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'order_num'   => 'required|integer',
            'is_optional' => 'boolean'
        ]);

        $data = $request->all();
        $data['is_optional'] = $request->is_optional ?? false;
        
        $level = Level::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'تم إنشاء المستوى بنجاح',
            'data'    => $level
        ]);
    }

    // 4️⃣ إضافة درس + فيديو + أسئلة الاختبار التابع له
    public function storeLessonWithQuiz(Request $request)
    {
        $request->validate([
            'level_id'     => 'required|exists:levels,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'video'        => 'nullable|file|mimes:mp4,mov,avi,mkv,wmv|max:500000',
            'video_url'    => 'nullable|string',
            'thumbnail'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'order_num'    => 'required|integer',
            'is_optional'  => 'nullable',
            'questions'    => 'nullable|array',
        ]);

        try {
            // رفع ملف الفيديو إن وجد أو أخذ الرابط
            $videoPath = null;
            if ($request->hasFile('video')) {
                $videoPath = $request->file('video')->store('lessons/videos', 'public');
                Log::info('Video uploaded to: ' . $videoPath);
            } elseif ($request->filled('video_url')) {
                $videoPath = $request->video_url;
            }

            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('lessons/thumbnails', 'public');
                Log::info('Thumbnail uploaded to: ' . $thumbnailPath);
            }
            
            $isOptional = $request->is_optional === 'true' || $request->is_optional === '1' || $request->is_optional === true || $request->is_optional === 1;

            $lesson = Lesson::create([
                'level_id'    => $request->level_id,
                'title'       => $request->title,
                'description' => $request->description,
                'video_url'   => $videoPath,
                'thumbnail'   => $thumbnailPath,
                'order_num'   => $request->order_num,
                'is_optional' => $isOptional,
            ]);

            // إضافة الأسئلة إن وجدت مع الدرس بداخل نفس النموذج
            if ($request->has('questions') && is_array($request->questions)) {
                foreach ($request->questions as $qData) {
                    $question = $lesson->questions()->create([
                        'question_text' => $qData['question_text']
                    ]);

                    if (isset($qData['options']) && is_array($qData['options'])) {
                        foreach ($qData['options'] as $optData) {
                            $isCorrect = $optData['is_correct'] ?? false;
                            // Handle string values from multipart form data
                            if (is_string($isCorrect)) {
                                $isCorrect = in_array(strtolower($isCorrect), ['true', '1', 'yes']);
                            }
                            $question->options()->create([
                                'option_text' => $optData['option_text'],
                                'is_correct'  => $isCorrect,
                            ]);
                        }
                    }
                }
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم حفظ الدرس مع الفيديو والأسئلة بنجاح',
                'data'    => $lesson->load('questions.options')
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating lesson: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'فشل في حفظ الدرس: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 5️⃣ عرض كافة المستخدمين واشتراكاتهم المدفوعة فقط
    public function getUsers(Request $request)
    {
        $users = User::select('id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'birth_date', 'created_at')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $users
        ]);
    }

    // 6️⃣ تفاصيل مستخدم كاملة (بيانات الحساب الكاملة من قاعدة البيانات)
    public function showUser(Request $request, $id)
    {
        $fields = [
            'id', 'first_name', 'middle_name', 'last_name', 'name',
            'email', 'email_verified_at', 'phone', 'birth_date',
            'is_active', 'created_at', 'updated_at',
        ];
        if (Schema::hasColumn('users', 'avatar')) {
            $fields[] = 'avatar';
        }

        $user = User::select($fields)->findOrFail($id);

        $userData = $user->toArray();
        // حالة تأكيد البريد الإلكتروني — جاهزة لدعم نظام التحقق لاحقاً
        $userData['email_verified'] = !is_null($user->email_verified_at);
        // رابط صورة الحساب إن وجدت
        $userData['avatar_url'] = (!empty($user->avatar) && Schema::hasColumn('users', 'avatar'))
            ? $request->getSchemeAndHttpHost() . '/storage/' . $user->avatar
            : null;

        return response()->json([
            'status' => true,
            'data'   => $userData
        ]);
    }

    // 7️⃣ تفعيل/إلغاء تفعيل الكورس (Publish Toggle)
    public function togglePublish($id)
    {
        $course = Course::findOrFail($id);

        // حفظ الحالة القديمة قبل التبديل
        $wasComingSoon = (bool) $course->is_coming_soon;

        $course->is_coming_soon = !$course->is_coming_soon;
        $course->save();

        // 🎉 عند نشر الكورس (انتقال فعلي من is_coming_soon=true إلى false فقط):
        // أرسل إشعاراً للطلاب المحجوزين عبر Queue.
        if ($wasComingSoon && !$course->is_coming_soon) {
            $this->notifyCoursePublished($course);
        }

        return response()->json([
            'status' => true,
            'message' => $course->is_coming_soon ? 'تم إخفاء الكورس (وضع الانتظار)' : 'تم نشر الكورس بنجاح',
            'data' => $course
        ]);
    }

    /**
     * إشعار "الكورس أصبح متاحاً" للطلاب الذين حجزوا الكورس فقط.
     * يُرسل عبر Queue لمنع Timeout عند وجود عدد كبير من المستخدمين.
     * يستخدم type = course_available لمنع التكرار عبر PushNotificationService.
     */
    private function notifyCoursePublished(Course $course): void
    {
        try {
            // فقط المستخدمون الذين حجزوا الكورس (course_reservations)
            $userIds = \DB::table('course_reservations')
                ->where('course_id', $course->id)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($userIds)) {
                \Log::info("Course #{$course->id} published — no reservations found, skipping notification.");
                return;
            }

            $title = 'الكورس أصبح متاحًا 🎉';
            $body = "الكورس '{$course->title}' الذي قمت بحجزه أصبح متاحًا الآن.";

            \App\Jobs\SendPushNotificationJob::dispatch(
                $userIds,
                $title,
                $body,
                [
                    'type' => 'course_available',
                    'course_id' => (string) $course->id,
                ],
                null, // لا توجد صورة
                $course->id,
                'course_available', // type for de-duplication
                null // لا يوجد سجل NotificationSend لهذا النوع
            );

            \Log::info("Course #{$course->id} published — dispatched notification job for " . count($userIds) . " reserved users.");
        } catch (\Throwable $e) {
            // فشل الإشعارات لا يمنع نشر الكورس أبداً
            \Log::error('notifyCoursePublished error for course #' . $course->id . ': ' . $e->getMessage());
        }
    }

    // 7️⃣ حذف درس مع ملفاته وأسئلته
    public function deleteLesson($id)
    {
        try {
            $lesson = Lesson::with('questions.options')->findOrFail($id);
            
            // Delete video file from storage
            if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL)) {
                if (Storage::disk('public')->exists($lesson->video_url)) {
                    Storage::disk('public')->delete($lesson->video_url);
                    Log::info('Deleted video: ' . $lesson->video_url);
                }
            }
            
            // Delete thumbnail file from storage
            if ($lesson->thumbnail) {
                if (Storage::disk('public')->exists($lesson->thumbnail)) {
                    Storage::disk('public')->delete($lesson->thumbnail);
                    Log::info('Deleted thumbnail: ' . $lesson->thumbnail);
                }
            }

            // Delete questions and options
            foreach ($lesson->questions as $question) {
                $question->options()->delete();
                $question->delete();
            }

            $lesson->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف الدرس وملفاته بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting lesson #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'فشل في حذف الدرس: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 8️⃣ حذف مستوى بجميع دروسه وملفاته وأسئلته
    public function deleteLevel($id)
    {
        try {
            $level = Level::with('lessons.questions.options')->findOrFail($id);

            foreach ($level->lessons as $lesson) {
                // Delete video file
                if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL)) {
                    if (Storage::disk('public')->exists($lesson->video_url)) {
                        Storage::disk('public')->delete($lesson->video_url);
                        Log::info('Deleted video: ' . $lesson->video_url);
                    }
                }
                
                // Delete thumbnail file
                if ($lesson->thumbnail) {
                    if (Storage::disk('public')->exists($lesson->thumbnail)) {
                        Storage::disk('public')->delete($lesson->thumbnail);
                        Log::info('Deleted thumbnail: ' . $lesson->thumbnail);
                    }
                }
                
                // Delete questions and options
                foreach ($lesson->questions as $question) {
                    $question->options()->delete();
                    $question->delete();
                }

                $lesson->delete();
            }

            $level->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف المستوى ومحتوياته بالكامل'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting level #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'فشل في حذف المستوى: ' . $e->getMessage(),
            ], 500);
        }
    }
}