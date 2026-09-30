<?php

namespace App\Http\Controllers\Api;

/// الـ AdminContentController
/// ده العصب الرئيسي لتطبيق الإدارة (code_shell_admin).
/// مسؤول عن إضافة/تعديل/حذف: الأقسام، الكورسات، المستويات، والدروس.
/// وكمان بيعرض بيانات المستخدمين والحجوزات.
/// ⚠️ مهم: كل الـ Routes اللي بتشاور على الـ Controller ده محمية بـ auth:sanctum في routes/api.php
/// وأي تعديل هنا هيسمّع في الـ Providers والـ Services في تطبيق الأدمن (Flutter).

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\CoursePlan;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\PlanLesson;
use App\Models\UploadTask;
use App\Models\User;
use App\Services\PricingService;
use App\Services\VideoProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AdminContentController extends Controller
{
    // 0️⃣ جلب جميع الكورسات (بما فيها غير المنشورة)
    public function getCourses(Request $request)
    {
        // تأكد من وجود كورس البكالوريا بالسيرفر للأدمن
        try { Course::findCourseSafely(10); } catch (\Exception $e) {}

        // تنظيف تلقائي: دمج أي كورسات بكالوريا مكررة في كورس واحد (لا يكلف شيئاً عند عدم وجود تكرار)
        try { Course::deduplicateBaccalaureate(); } catch (\Throwable $e) {
            \Log::warning('Baccalaureate deduplication skipped: ' . $e->getMessage());
        }

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

        try {
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
        } catch (\Illuminate\Database\QueryException $e) {
            // قيد العنوان الفريد: منع إنشاء كورسات بنفس العنوان بدل 500 غامضة
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'يوجد كورس بنفس العنوان بالفعل. يرجى استخدام عنوان مختلف أو تعديل الكورس الموجود.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'status'  => true,
            'message' => 'تمت إضافة الكورس بنجاح',
            'data'    => $course
        ]);
    }

    // 2️⃣.ب تعديل كورس (البيانات الأساسية + الأسعار متعددة العملات)
    public function updateCourse(Request $request, $id)
    {
        $course = Course::findCourseSafely($id);

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

        try {
            $course->save();
        } catch (\Illuminate\Database\QueryException $e) {
            // قيد العنوان الفريد: رسالة واضحة بدل خطأ سيرفر غامض
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'يوجد كورس آخر بنفس العنوان. يرجى استخدام عنوان مختلف.',
                ], 422);
            }
            throw $e;
        }

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
            'is_optional' => 'boolean',
            'group_id'    => 'nullable|exists:course_groups,id'
        ]);

        $data = $request->all();
        $data['is_optional'] = $request->is_optional ?? false;

        $level = Level::create($data);

        // إذا تم تحديد مجموعة، قم بربط المستوى بالمجموعة
        if ($request->has('group_id') && $request->group_id) {
            $group = \App\Models\CourseGroup::find($request->group_id);
            if ($group) {
                $group->level_id = $level->id;
                $group->save();
            }
        }

        // Send silent push to update content
        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', 'content_updates')
                ->withData(['action' => 'refresh_content']);
            app('firebase.messaging')->send($message);
        } catch (\Throwable $e) {
            Log::error('FCM update error: ' . $e->getMessage());
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم إنشاء المستوى بنجاح',
            'data'    => $level
        ]);
    }

    // 4️⃣ إضافة درس + فيديو + أسئلة الاختبار التابع له
    /// ⚠️ Flow الرفع (Upload Flow) للفيديوهات:
    /// الدرس ممكن يستقبل رابط (URL) أو ملف فيديو حقيقي.
    /// لو ملف:
    /// 1. بيرفع الملف على Cloudflare R2 (Storage: disk('r2')).
    /// 2. بيستخدم `VideoProcessor` علشان يعمل Fast-Start (ينقل الـ moov atom لأول الملف)
    ///    ده بيخلي تطبيق الطالب يقدر يعمل Stream للفيديو فوراً من غير ما يستنى تحميله بالكامل.
    /// 3. بيسجل الأسئلة (Quiz) التابعة للدرس في نفس الـ Request علشان يقلل الـ API calls.
    /// 4. بيبعت Firebase Notification صامت (Silent Push) علشان يخلي أجهزة الطلاب
    ///    تعمل Refresh للمحتوى تلقائياً بدون ما اليوزر يعمل Pull to refresh.
    public function storeLessonWithQuiz(Request $request)
    {
        $request->validate([
            'level_id'     => 'required|exists:levels,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'video'        => 'nullable|file|mimes:mp4,mov,avi,mkv,wmv|max:1048576',
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
                $file = $request->file('video');
                $key = $file->store('lessons/videos', 'r2');
                Log::info('Video uploaded to R2: ' . $key);

                // تحويل MP4 إلى Fast-Start (moov في البداية) حتى لا يتوقف
                // البث بعد ثوانٍ من التشغيل — بدون إعادة ترميز (لا فقد جودة).
                try {
                    $processor = new VideoProcessor();
                    $processed = $processor->fastStart($file->getRealPath(), $file->getClientOriginalName());
                    if ($processed !== $file->getRealPath()) {
                        $disk = Storage::disk('r2');
                        $disk->put($key, fopen($processed, 'rb'), ['ContentType' => 'video/mp4']);
                        @unlink($processed);
                        Log::info('Video fast-start applied: ' . $key);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Video fast-start skipped: ' . $e->getMessage());
                }

                $videoPath = $key;
            } elseif ($request->filled('video_url')) {
                $videoPath = $request->video_url;
            }

            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('lessons/thumbnails', 'r2');
                Log::info('Thumbnail uploaded to R2: ' . $thumbnailPath);
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

            // Send silent push to update content
            try {
                $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', 'content_updates')
                    ->withData(['action' => 'refresh_content']);
                app('firebase.messaging')->send($message);
            } catch (\Throwable $e) {
                Log::error('FCM update error: ' . $e->getMessage());
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

    // 4️⃣.ب رفع مقطع واحد من الفيديو (Chunked Upload for Lessons):
    // يرفع تطبيق الأدمن الفيديو على شكل مقاطع صغيرة لتجنب الحد الأقصى للطلب
    // ويدعم استكمال الرفع بعد انقطاع الاتصال
    public function uploadLessonChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string|max:100',
            'index' => 'required|integer|min:0|max:20000',
            'total_chunks' => 'required|integer',
            'filename' => 'nullable|string',
            'data' => 'required|file|max:10240', // 10MB per chunk
        ]);

        $disk = Storage::disk('local');
        $chunkDir = 'chunks/' . $request->upload_id;
        
        // تأكد من وجود المجلد
        if (!$disk->exists($chunkDir)) {
            $disk->makeDirectory($chunkDir);
        }

        $path = $request->file('data')->storeAs(
            $chunkDir,
            (int) $request->index . '.part',
            'local'
        );

        // تحديث وقت آخر نشاط للرفع
        $disk->put($chunkDir . '/.last_activity', now()->toIso8601String());

        // تحديث أو إنشاء مهمة الرفع في قاعدة البيانات
        $uploadTask = UploadTask::where('upload_id', $request->upload_id)->first();
        if (!$uploadTask) {
            $uploadTask = \App\Models\UploadTask::create([
                'upload_id' => $request->upload_id,
                'admin_id' => auth()->id(),
                'task_type' => 'lesson',
                'filename' => $request->filename,
                'total_chunks' => (int) $request->total_chunks,
                'status' => 'uploading',
                'started_at' => now(),
            ]);
        }

        // تحديث التقدم
        $receivedChunks = 0;
        for ($i = 0; $i < $uploadTask->total_chunks; $i++) {
            if ($disk->exists($chunkDir . '/' . $i . '.part')) {
                $receivedChunks++;
            }
        }

        $uploadTask->received_chunks = $receivedChunks;
        $uploadTask->progress = $uploadTask->total_chunks > 0 ? round(($receivedChunks / $uploadTask->total_chunks) * 100, 2) : 0;
        $uploadTask->status = 'uploading';
        $uploadTask->save();

        return response()->json([
            'status' => true,
            'received' => (int) $request->index,
            'path' => $path,
            'progress' => $uploadTask->progress,
        ], 200);
    }

    // 4️⃣.ج التحقق من حالة الرفع (Upload Status Check):
    // يرجع عدد المقاطع المستلمة من إجمالي المقاطع المطلوبة
    public function checkUploadStatus(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string|max:100',
            'total_chunks' => 'required|integer',
        ]);

        $disk = Storage::disk('local');
        $chunkDir = 'chunks/' . $request->upload_id;
        $totalChunks = (int) $request->total_chunks;

        $received = 0;
        $missing = [];

        for ($i = 0; $i < $totalChunks; $i++) {
            if ($disk->exists($chunkDir . '/' . $i . '.part')) {
                $received++;
            } else {
                $missing[] = $i;
            }
        }

        // تحقق من آخر نشاط (لتحديد الرفعات المنتهية)
        $lastActivity = null;
        if ($disk->exists($chunkDir . '/.last_activity')) {
            $lastActivity = $disk->get($chunkDir . '/.last_activity');
        }

        return response()->json([
            'status' => true,
            'upload_id' => $request->upload_id,
            'total_chunks' => $totalChunks,
            'received_chunks' => $received,
            'missing_chunks' => $missing,
            'progress' => $totalChunks > 0 ? round(($received / $totalChunks) * 100, 2) : 0,
            'last_activity' => $lastActivity,
        ], 200);
    }

    // 4️⃣.د استكمال رفع درس مجزأ (Chunked Upload):
    // الفيديو يُقسم في المتصفح لمقاطع صغيرة تُرفع عبر /admin/upload-lesson-chunk،
    // ثم هذا المسار يجمعها ويكمل نفس مسار R2 + Fast-Start المتبع في storeLessonWithQuiz.
    // يتيح: الإيقاف المؤقت، استكمال الرفع بعد انقطاع النت أو إعادة تحميل الصفحة.
    public function completeChunkedLesson(Request $request)
    {
        $request->validate([
            'upload_id'    => 'required|string|max:100',
            'total_chunks' => 'required|integer|min:1|max:20000',
            'filename'     => 'required|string|max:255',
            'level_id'     => 'required|exists:levels,id',
            'title'        => 'required|string|max:255',
            'order_num'    => 'required|integer',
            'description'  => 'nullable|string',
            'is_optional'  => 'nullable',
            'questions'    => 'nullable',
            'thumbnail'    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'video_url'    => 'nullable|string',
        ]);

        $ext = strtolower(pathinfo($request->filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'wmv'])) {
            return response()->json([
                'status'  => false,
                'message' => 'صيغة ملف الفيديو غير مدعومة (المسموح: mp4, mov, avi, mkv, wmv).',
            ], 422);
        }

        $disk = Storage::disk('local');
        $chunkDir = 'chunks/' . $request->upload_id;
        $totalChunks = (int) $request->total_chunks;

        try {
            // 1. التأكد من وصول كل المقاطع
            $missing = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!$disk->exists($chunkDir . '/' . $i . '.part')) {
                    $missing[] = $i;
                }
            }
            if (!empty($missing)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'بعض مقاطع الفيديو لم تصل بعد. أعد المحاولة لاستكمالها.',
                    'missing' => $missing,
                ], 422);
            }

            // 2. تجميع المقاطع بالترتيب في ملف مؤقت
            $mergedTmp = $disk->path($chunkDir . '/merged.tmp');
            $out = @fopen($mergedTmp, 'wb');
            if (!$out) {
                throw new \Exception('تعذر إنشاء ملف مؤقت للتجميع.');
            }
            for ($i = 0; $i < $totalChunks; $i++) {
                $in = @fopen($disk->path($chunkDir . '/' . $i . '.part'), 'rb');
                if (!$in) {
                    fclose($out);
                    throw new \Exception('تعذر قراءة المقطع رقم ' . ($i + 1));
                }
                while (!feof($in)) {
                    fwrite($out, fread($in, 1024 * 512));
                }
                fclose($in);
            }
            fclose($out);

            // 3. غلاف UploadedFile للمؤقت ليغذي نفس مسار R2 المتبع
            // مع نوع MIME صحيح حسب الامتداد الأصلي (حتى لا يُخمَّن .bin)
            $mimeMap = [
                'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo',
                'mkv' => 'video/x-matroska', 'wmv' => 'video/x-ms-wmv',
            ];
            $videoFile = new \Illuminate\Http\UploadedFile(
                $mergedTmp,
                $request->filename,
                $mimeMap[$ext] ?? 'application/octet-stream',
                null,
                true
            );

            $key = $videoFile->store('lessons/videos', 'r2');
            Log::info('Chunked video uploaded to R2: ' . $key);

            // 4. Fast-Start (نقل moov لأول الملف) بدون إعادة ترميز
            try {
                $processor = new \App\Services\VideoProcessor();
                $processed = $processor->fastStart($videoFile->getRealPath(), $videoFile->getClientOriginalName());
                if ($processed !== $videoFile->getRealPath()) {
                    Storage::disk('r2')->put($key, fopen($processed, 'rb'), ['ContentType' => 'video/mp4']);
                    @unlink($processed);
                    Log::info('Video fast-start applied: ' . $key);
                }
            } catch (\Throwable $e) {
                Log::warning('Video fast-start skipped: ' . $e->getMessage());
            }
            $videoPath = $key;

            // 5. الصورة المصغرة
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('lessons/thumbnails', 'r2');
                Log::info('Thumbnail uploaded to R2: ' . $thumbnailPath);
            }

            $isOptional = $request->is_optional === 'true' || $request->is_optional === '1' || $request->is_optional === true || $request->is_optional === 1;

            // 6. إنشاء الدرس
            $lesson = Lesson::create([
                'level_id'    => $request->level_id,
                'title'       => $request->title,
                'description' => $request->description,
                'video_url'   => $videoPath,
                'thumbnail'   => $thumbnailPath,
                'order_num'   => $request->order_num,
                'is_optional' => $isOptional,
            ]);

            // 7. الأسئلة (تصل كسلسلة JSON من FormData أو مصفوفة)
            $questions = $request->questions;
            if (is_string($questions)) {
                $questions = json_decode($questions, true);
            }
            if (is_array($questions)) {
                foreach ($questions as $qData) {
                    if (!is_array($qData) || empty($qData['question_text'])) continue;
                    $question = $lesson->questions()->create([
                        'question_text' => $qData['question_text'],
                    ]);
                    if (isset($qData['options']) && is_array($qData['options'])) {
                        foreach ($qData['options'] as $optData) {
                            $isCorrect = $optData['is_correct'] ?? false;
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

            // 8. تحديث مهمة الرفع كمكتملة
            $uploadTask = UploadTask::where('upload_id', $request->upload_id)->first();
            if ($uploadTask) {
                $uploadTask->status = 'completed';
                $uploadTask->progress = 100;
                $uploadTask->completed_at = now();
                $uploadTask->save();
            }

            // 9. تنظيف مجلد المقاطع المؤقت
            try { $disk->deleteDirectory($chunkDir); } catch (\Throwable $e) {}

            // 10. إشعار صامت للطلاب بتحديث المحتوى
            try {
                $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', 'content_updates')
                    ->withData(['action' => 'refresh_content']);
                app('firebase.messaging')->send($message);
            } catch (\Throwable $e) {
                Log::error('FCM update error: ' . $e->getMessage());
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم رفع الدرس وحفظ الاختبارات بنجاح',
                'data'    => $lesson->load('questions.options'),
            ]);
        } catch (\Exception $e) {
            Log::error('Chunked lesson completion failed: ' . $e->getMessage());
            
            // تحديث مهمة الرفع كفاشلة
            $uploadTask = UploadTask::where('upload_id', $request->upload_id)->first();
            if ($uploadTask) {
                $uploadTask->status = 'failed';
                $uploadTask->error_message = $e->getMessage();
                $uploadTask->save();
            }
            
            return response()->json([
                'status'  => false,
                'message' => 'فشل حفظ الدرس: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 4️⃣.ه عرض جميع مهام الرفع (Upload Tasks List)
    public function getUploadTasks(Request $request)
    {
        $tasks = UploadTask::with('admin')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $tasks,
        ]);
    }

    // 4️⃣.و حذف مهمة رفع (Delete Upload Task)
    public function deleteUploadTask($id)
    {
        $task = UploadTask::find($id);
        if (!$task) {
            return response()->json([
                'status' => false,
                'message' => 'مهمة الرفع غير موجودة',
            ], 404);
        }

        // تنظيف الملفات المؤقتة إذا وُجدت
        if ($task->upload_id) {
            $disk = Storage::disk('local');
            $chunkDir = 'chunks/' . $task->upload_id;
            try { $disk->deleteDirectory($chunkDir); } catch (\Throwable $e) {}
        }

        $task->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف مهمة الرفع بنجاح',
        ]);
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
        $course = Course::findCourseSafely($id);

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
                if (Storage::disk('r2')->exists($lesson->video_url)) {
                    Storage::disk('r2')->delete($lesson->video_url);
                } elseif (Storage::disk('public')->exists($lesson->video_url)) {
                    Storage::disk('public')->delete($lesson->video_url);
                }
                Log::info('Deleted video: ' . $lesson->video_url);
            }
            
            // Delete thumbnail file from storage
            if ($lesson->thumbnail) {
                if (Storage::disk('r2')->exists($lesson->thumbnail)) {
                    Storage::disk('r2')->delete($lesson->thumbnail);
                } elseif (Storage::disk('public')->exists($lesson->thumbnail)) {
                    Storage::disk('public')->delete($lesson->thumbnail);
                }
                Log::info('Deleted thumbnail: ' . $lesson->thumbnail);
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
                    if (Storage::disk('r2')->exists($lesson->video_url)) {
                        Storage::disk('r2')->delete($lesson->video_url);
                    } elseif (Storage::disk('public')->exists($lesson->video_url)) {
                        Storage::disk('public')->delete($lesson->video_url);
                    }
                    Log::info('Deleted video: ' . $lesson->video_url);
                }
                
                // Delete thumbnail file
                if ($lesson->thumbnail) {
                    if (Storage::disk('r2')->exists($lesson->thumbnail)) {
                        Storage::disk('r2')->delete($lesson->thumbnail);
                    } elseif (Storage::disk('public')->exists($lesson->thumbnail)) {
                        Storage::disk('public')->delete($lesson->thumbnail);
                    }
                    Log::info('Deleted thumbnail: ' . $lesson->thumbnail);
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

    // 9️⃣ حذف مستخدم نهائياً
    public function deleteUser($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Delete avatar if exists
            if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                Storage::disk('public')->delete($user->avatar_url);
            }

            // Send silent push to force logout before deleting the user
            if ($user->fcm_token) {
                try {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $user->fcm_token)
                        ->withData(['action' => 'force_logout']);
                    app('firebase.messaging')->send($message);
                } catch (\Throwable $e) {
                    Log::error('FCM force logout error for user #' . $id . ': ' . $e->getMessage());
                }
            }

            // Database relationships (Progress, Reservations, EmailVerifications, PasswordResets)
            // Should be handled by ON DELETE CASCADE in DB, but just in case we force delete the user.
            $user->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف الحساب نهائياً بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting user #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'فشل في حذف الحساب: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 🔟 إدارة الباقات (Plans) للكورسات
    /**
     * جلب جميع الباقات لكورس معين
     */
    public function getCoursePlans($courseId)
    {
        $course = Course::findCourseSafely($courseId);
        $plans = CoursePlan::where('course_id', $courseId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $plans
        ]);
    }

    /**
     * إضافة باقة جديدة لكورس
     */
    public function storeCoursePlan(Request $request, $courseId)
    {
        $course = Course::findCourseSafely($courseId);

        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'required|string|max:255|unique:course_plans,slug,NULL,id,course_id,' . $courseId,
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:3',
            'duration_days' => 'required|integer|min:1',
            'duration_type' => 'required|string|in:days,months,years',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'features' => 'nullable|array',
        ]);

        $plan = CoursePlan::create([
            'course_id' => $courseId,
            'name' => $request->name,
            'name_en' => $request->name_en,
            'slug' => $request->slug,
            'description' => $request->description,
            'price' => $request->price,
            'currency' => $request->currency,
            'duration_days' => $request->duration_days,
            'duration_type' => $request->duration_type,
            'is_active' => $request->is_active ?? true,
            'sort_order' => $request->sort_order ?? 0,
            'features' => $request->features,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة الباقة بنجاح',
            'data' => $plan
        ]);
    }

    /**
     * تعديل باقة موجودة
     */
    public function updateCoursePlan(Request $request, $id)
    {
        $plan = CoursePlan::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:course_plans,slug,' . $id . ',id',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric',
            'currency' => 'sometimes|string|max:3',
            'duration_days' => 'sometimes|integer|min:1',
            'duration_type' => 'sometimes|string|in:days,months,years',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'features' => 'nullable|array',
        ]);

        if ($request->has('name')) $plan->name = $request->name;
        if ($request->has('name_en')) $plan->name_en = $request->name_en;
        if ($request->has('slug')) $plan->slug = $request->slug;
        if ($request->has('description')) $plan->description = $request->description;
        if ($request->has('price')) $plan->price = $request->price;
        if ($request->has('currency')) $plan->currency = $request->currency;
        if ($request->has('duration_days')) $plan->duration_days = $request->duration_days;
        if ($request->has('duration_type')) $plan->duration_type = $request->duration_type;
        if ($request->has('is_active')) $plan->is_active = $request->is_active;
        if ($request->has('sort_order')) $plan->sort_order = $request->sort_order;
        if ($request->has('features')) $plan->features = $request->features;

        $plan->save();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الباقة بنجاح',
            'data' => $plan
        ]);
    }

    /**
     * حذف باقة
     */
    public function deleteCoursePlan($id)
    {
        try {
            $plan = CoursePlan::findOrFail($id);
            $plan->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف الباقة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting plan #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'فشل في حذف الباقة: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 1️⃣1️⃣ إدارة ربط المحاضرات بالباقات (Plan Lessons)
    /**
     * جلب المحاضرات المرتبطة بباقة معينة
     */
    public function getPlanLessons($planId)
    {
        $plan = CoursePlan::findOrFail($planId);
        $planLessons = PlanLesson::where('plan_id', $planId)
            ->with(['lesson', 'level', 'group'])
            ->get();

        return response()->json([
            'status' => true,
            'data' => $planLessons
        ]);
    }

    /**
     * ربط محاضرة بباقة (أو مجموعة/مستوى)
     */
    public function assignLessonToPlan(Request $request, $planId)
    {
        $plan = CoursePlan::findOrFail($planId);

        $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'level_id' => 'nullable|exists:levels,id',
            'group_id' => 'nullable|exists:course_groups,id',
            'is_accessible' => 'sometimes|boolean',
        ]);

        $planLesson = PlanLesson::updateOrCreate(
            [
                'plan_id' => $planId,
                'lesson_id' => $request->lesson_id,
            ],
            [
                'level_id' => $request->level_id,
                'group_id' => $request->group_id,
                'is_accessible' => $request->is_accessible ?? true,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'تم ربط المحاضرة بالباقة بنجاح',
            'data' => $planLesson
        ]);
    }

    /**
     * إزالة ربط محاضرة من باقة
     */
    public function removeLessonFromPlan($id)
    {
        try {
            $planLesson = PlanLesson::findOrFail($id);
            $planLesson->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم إزالة المحاضرة من الباقة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing lesson from plan #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'فشل في إزالة المحاضرة: ' . $e->getMessage(),
            ], 500);
        }
    }
}