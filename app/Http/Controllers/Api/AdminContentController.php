<?php
namespace App\Http\Controllers\Api;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminContentController extends Controller
{
    // 0️⃣ جلب جميع الكورسات (بما فيها غير المنشورة)
    public function getCourses(Request $request)
    {
        // ⚡ كاش 5 دقائق لتسريع تحميل لوحة الأدمن
        return \Cache::remember('admin_all_courses_v2', 300, function () {
            // Admin needs to see all courses to manage them
            $courses = Course::with('category')->orderBy('id', 'desc')->get();
            $courseIds = $courses->pluck('id');

            // ⚡ استعلامات مجمعة تمنع استعلامات Accessors لكل كورس (كانت سبب 16 ثانية)
            $levelCounts = DB::table('levels')->whereIn('course_id', $courseIds)->groupBy('course_id')->selectRaw('course_id, COUNT(*) as c')->pluck('c', 'course_id');
            $lessonCounts = DB::table('lessons')->join('levels', 'lessons.level_id', '=', 'levels.id')->whereIn('levels.course_id', $courseIds)->groupBy('levels.course_id')->selectRaw('levels.course_id as cid, COUNT(*) as c')->pluck('c', 'cid');
            $subCounts = DB::table('course_subscriptions')->whereIn('course_id', $courseIds)->where(function ($q) { $q->whereNull('subscription_status')->orWhere('subscription_status', 'active'); })->groupBy('course_id')->selectRaw('course_id, COUNT(*) as c')->pluck('c', 'course_id');
            $resCounts = DB::table('course_reservations')->whereIn('course_id', $courseIds)->groupBy('course_id')->selectRaw('course_id, COUNT(*) as c')->pluck('c', 'course_id');

            $courses->each(function ($course) use ($levelCounts, $lessonCounts, $subCounts, $resCounts) {
                $cid = $course->id;
                $course->setAttribute('levels_count', (int) ($levelCounts[$cid] ?? 0));
                $course->setAttribute('lessons_count', (int) ($lessonCounts[$cid] ?? 0));
                $course->setAttribute('students_count', (int) ($subCounts[$cid] ?? 0) + 120);
                $course->setAttribute('subscriptions_count', (int) ($subCounts[$cid] ?? 0));
                $course->setAttribute('reservations_count', (int) ($resCounts[$cid] ?? 0));
            });

            return response()->json([
                'status' => true,
                'data'   => $courses
            ]);
        });
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
            'original_price' => 'nullable|numeric',
            'has_discount'   => 'nullable|boolean',
            'is_coming_soon' => 'boolean',
            'prices'         => 'nullable',
            'prices.*'       => 'nullable|numeric',
        ]);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $prices = PricingService::normalizePrices($request->input('prices'));

        // حساب نسبة التخفيض تلقائياً
        $discountPercentage = null;
        $hasDiscount = $request->has_discount ?? false;
        if ($hasDiscount && $request->original_prices && $request->discount_prices) {
            $originalPrices = PricingService::normalizePrices($request->input('original_prices'));
            $discountPrices = PricingService::normalizePrices($request->input('discount_prices'));
            
            // حساب متوسط نسبة التخفيض من جميع العملات
            $totalDiscount = 0;
            $count = 0;
            foreach ($originalPrices as $code => $originalPrice) {
                if (isset($discountPrices[$code]) && $originalPrice > 0 && $discountPrices[$code] < $originalPrice) {
                    $totalDiscount += (($originalPrice - $discountPrices[$code]) / $originalPrice) * 100;
                    $count++;
                }
            }
            $discountPercentage = $count > 0 ? round($totalDiscount / $count) : null;
        }

        try {
            $course = Course::create([
                'category_id'    => $request->category_id,
                'title'          => $request->title,
                'description'    => $request->description,
                'thumbnail'      => $thumbnailPath,
                'is_free'        => $request->is_free,
                // العمود القديم للتوافق؛ المصدر الحقيقي هو مصفوفة prices (EGP افتراضياً).
                'price'          => $request->is_free ? 0 : ($request->price ?? $prices['EGP'] ?? 0),
                'original_price' => $request->original_price ?? ($originalPrices['EGP'] ?? null),
                'discount_percentage' => $discountPercentage,
                'has_discount'   => $hasDiscount,
                'prices'         => $prices,
                'discount_prices' => $request->discount_prices ?? null,
                'original_prices' => $request->original_prices ?? null,
                'is_coming_soon' => $request->is_coming_soon ?? false,
                'is_active'      => true,
                'subscription_duration_days' => $request->subscription_duration_days ?? 90,
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
            'price'          => 'sometimes|numeric',
            'original_price' => 'sometimes|numeric',
            'has_discount'   => 'sometimes|boolean',
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
        if ($request->has('price')) {
            $course->price = $request->price;
        }
        if ($request->has('original_price')) {
            $course->original_price = $request->original_price;
        }
        if ($request->has('has_discount')) {
            $course->has_discount = $request->has_discount;
        }
        // حساب نسبة التخفيض تلقائياً عند التعديل
        if ($request->has('has_discount') && $request->has('original_prices') && $request->has('discount_prices')) {
            if ($request->has_discount) {
                $originalPrices = PricingService::normalizePrices($request->input('original_prices'));
                $discountPrices = PricingService::normalizePrices($request->input('discount_prices'));
                
                // حساب متوسط نسبة التخفيض من جميع العملات
                $totalDiscount = 0;
                $count = 0;
                foreach ($originalPrices as $code => $originalPrice) {
                    if (isset($discountPrices[$code]) && $originalPrice > 0 && $discountPrices[$code] < $originalPrice) {
                        $totalDiscount += (($originalPrice - $discountPrices[$code]) / $originalPrice) * 100;
                        $count++;
                    }
                }
                $course->discount_percentage = $count > 0 ? round($totalDiscount / $count) : null;
                $course->original_prices = $originalPrices;
                $course->discount_prices = $discountPrices;
            } else {
                $course->discount_percentage = null;
                $course->original_prices = null;
                $course->discount_prices = null;
            }
        }
        if ($request->has('prices')) {
            $course->prices = PricingService::normalizePrices($request->input('prices'));
        }
        if ($request->has('subscription_duration_days')) {
            $course->subscription_duration_days = $request->subscription_duration_days;
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

    // 3️⃣ إضافة مستوى داخل كورس أو باقة
    public function storeLevel(Request $request)
    {
        $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'plan_id'     => 'nullable|exists:course_plans,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'what_you_will_learn' => 'nullable|string',
            'order_num'   => 'required|integer',
            'is_optional' => 'boolean'
        ]);

        $data = $request->all();
        $data['plan_id'] = $request->filled('plan_id') ? $request->plan_id : null;
        $data['is_optional'] = $request->is_optional ?? false;

        $level = Level::create($data);

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
            $videoPath = null;
            if ($request->hasFile('video')) {
                $file = $request->file('video');
                $key = $file->store('lessons/videos', 'r2');
                Log::info('Video uploaded to R2: ' . $key);

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
                'what_you_will_learn' => $request->what_you_will_learn,
                'video_url'   => $videoPath,
                'thumbnail'   => $thumbnailPath,
                'order_num'   => $request->order_num,
                'is_optional' => $isOptional,
            ]);

            if ($request->has('questions') && is_array($request->questions)) {
                foreach ($request->questions as $qData) {
                    $question = $lesson->questions()->create([
                        'question_text' => $qData['question_text']
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

    // 4️⃣.ب رفع مقطع واحد من الفيديو (Chunked Upload for Lessons)
    public function uploadLessonChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|string|max:100',
            'index' => 'required|integer|min:0|max:20000',
            'total_chunks' => 'required|integer',
            'filename' => 'nullable|string',
            'data' => 'required|file|max:10240',
        ]);

        $disk = Storage::disk('local');
        $chunkDir = 'chunks/' . $request->upload_id;
        
        if (!$disk->exists($chunkDir)) {
            $disk->makeDirectory($chunkDir);
        }

        $path = $request->file('data')->storeAs(
            $chunkDir,
            (int) $request->index . '.part',
            'local'
        );

        $disk->put($chunkDir . '/.last_activity', now()->toIso8601String());

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

    // 4️⃣.ج التحقق من حالة الرفع
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

    // 4️⃣.د استكمال رفع درس مجزأ (Chunked Upload)
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
                'message' => 'صيغة ملف الفيديو غير مدعومة.',
            ], 422);
        }

        $disk = Storage::disk('local');
        $chunkDir = 'chunks/' . $request->upload_id;
        $totalChunks = (int) $request->total_chunks;

        try {
            $missing = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!$disk->exists($chunkDir . '/' . $i . '.part')) {
                    $missing[] = $i;
                }
            }
            if (!empty($missing)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'بعض مقاطع الفيديو لم تصل بعد.',
                    'missing' => $missing,
                ], 422);
            }

            $mergedTmp = $disk->path($chunkDir . '/merged.tmp');
            $out = @fopen($mergedTmp, 'wb');
            if (!$out) {
                throw new \Exception('تعذر إنشاء ملف مؤقت للتجميع.');
            }
            for ($i = 0; $i < $totalChunks; $i++) {
                $in = @fopen($disk->path($chunkDir . '/' . $i . '.part'), 'rb');
                if (!$in) {
                    fclose($out);
                    throw new \Exception('تعذر قراءة المقطوعات.');
                }
                while (!feof($in)) {
                    fwrite($out, fread($in, 1024 * 512));
                }
                fclose($in);
            }
            fclose($out);

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

            try {
                $processor = new \App\Services\VideoProcessor();
                $processed = $processor->fastStart($videoFile->getRealPath(), $videoFile->getClientOriginalName());
                if ($processed !== $videoFile->getRealPath()) {
                    Storage::disk('r2')->put($key, fopen($processed, 'rb'), ['ContentType' => 'video/mp4']);
                    @unlink($processed);
                }
            } catch (\Throwable $e) {
                Log::warning('Video fast-start skipped: ' . $e->getMessage());
            }
            $videoPath = $key;

            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('lessons/thumbnails', 'r2');
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

            $uploadTask = UploadTask::where('upload_id', $request->upload_id)->first();
            if ($uploadTask) {
                $uploadTask->status = 'completed';
                $uploadTask->progress = 100;
                $uploadTask->completed_at = now();
                $uploadTask->save();
            }

            try { $disk->deleteDirectory($chunkDir); } catch (\Throwable $e) {}

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

    // 4️⃣.هـ عرض جميع مهام الرفع
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

    // 4️⃣.و حذف مهمة رفع
    public function deleteUploadTask($id)
    {
        $task = UploadTask::find($id);
        if (!$task) {
            return response()->json([
                'status' => false,
                'message' => 'مهمة الرفع غير موجودة',
            ], 404);
        }

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

    // 5️⃣ عرض كافة المستخدمين
    public function getUsers(Request $request)
    {
        $users = User::select('id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'birth_date', 'created_at')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $users
        ]);
    }

    // 6️⃣ تفاصيل مستخدم كاملة
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
        $userData['email_verified'] = !is_null($user->email_verified_at);
        $userData['avatar_url'] = (!empty($user->avatar) && Schema::hasColumn('users', 'avatar'))
            ? $request->getSchemeAndHttpHost() . '/storage/' . $user->avatar
            : null;

        return response()->json([
            'status' => true,
            'data'   => $userData
        ]);
    }

    // 7️⃣ تفعيل/إلغاء تفعيل الكورس
    public function togglePublish($id)
    {
        $course = Course::findCourseSafely($id);
        $wasComingSoon = (bool) $course->is_coming_soon;

        $course->is_coming_soon = !$course->is_coming_soon;
        $course->save();

        if ($wasComingSoon && !$course->is_coming_soon) {
            $this->notifyCoursePublished($course);
        }

        return response()->json([
            'status' => true,
            'message' => $course->is_coming_soon ? 'تم إخفاء الكورس (وضع الانتظار)' : 'تم نشر الكورس بنجاح',
            'data' => $course
        ]);
    }

    private function notifyCoursePublished(Course $course): void
    {
        try {
            $userIds = \DB::table('course_reservations')
                ->where('course_id', $course->id)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($userIds)) {
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
                null,
                $course->id,
                'course_available',
                null
            );
        } catch (\Throwable $e) {
            \Log::error('notifyCoursePublished error: ' . $e->getMessage());
        }
    }

    // 7️⃣.ب حذف درس مع ملفاته وأسئلته
    public function deleteLesson($id)
    {
        try {
            $lesson = Lesson::with('questions.options')->findOrFail($id);
            
            if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL)) {
                if (Storage::disk('r2')->exists($lesson->video_url)) {
                    Storage::disk('r2')->delete($lesson->video_url);
                } elseif (Storage::disk('public')->exists($lesson->video_url)) {
                    Storage::disk('public')->delete($lesson->video_url);
                }
            }
            
            if ($lesson->thumbnail) {
                if (Storage::disk('r2')->exists($lesson->thumbnail)) {
                    Storage::disk('r2')->delete($lesson->thumbnail);
                } elseif (Storage::disk('public')->exists($lesson->thumbnail)) {
                    Storage::disk('public')->delete($lesson->thumbnail);
                }
            }

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

    // 8️⃣ حذف مستوى بجميع دروسه
    public function deleteLevel($id)
    {
        try {
            $level = Level::with('lessons.questions.options')->findOrFail($id);

            foreach ($level->lessons as $lesson) {
                if ($lesson->video_url && !filter_var($lesson->video_url, FILTER_VALIDATE_URL)) {
                    if (Storage::disk('r2')->exists($lesson->video_url)) {
                        Storage::disk('r2')->delete($lesson->video_url);
                    } elseif (Storage::disk('public')->exists($lesson->video_url)) {
                        Storage::disk('public')->delete($lesson->video_url);
                    }
                }
                
                if ($lesson->thumbnail) {
                    if (Storage::disk('r2')->exists($lesson->thumbnail)) {
                        Storage::disk('r2')->delete($lesson->thumbnail);
                    } elseif (Storage::disk('public')->exists($lesson->thumbnail)) {
                        Storage::disk('public')->delete($lesson->thumbnail);
                    }
                }
                
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
            
            if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                Storage::disk('public')->delete($user->avatar_url);
            }

            if ($user->fcm_token) {
                try {
                    $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $user->fcm_token)
                        ->withData(['action' => 'force_logout']);
                    app('firebase.messaging')->send($message);
                } catch (\Throwable $e) {
                    Log::error('FCM force logout error: ' . $e->getMessage());
                }
            }

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

    public function storeCoursePlan(Request $request, $courseId)
    {
        $course = Course::findCourseSafely($courseId);

        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'original_price' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'currency' => 'sometimes|string|max:3',
            'duration_days' => 'sometimes|integer|min:1',
            'duration_type' => 'sometimes|string|in:days,months,years',
            'is_active' => 'sometimes',
            'sort_order' => 'sometimes|integer',
            'features' => 'nullable',
            'image' => 'nullable',
        ]);

        $slug = $request->slug;
        if (empty($slug)) {
            $slug = Str::slug($request->name ?: 'plan') . '-' . substr(uniqid(), -6);
        }
        while (CoursePlan::where('course_id', $courseId)->where('slug', $slug)->exists()) {
            $slug = Str::slug($request->name ?: 'plan') . '-' . rand(1000, 9999);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $stored = $file->store('plans', 'public');
            $imagePath = 'storage/' . $stored;
        } elseif ($request->filled('image') && is_string($request->image)) {
            $imagePath = $request->image;
        }

        $isFree = filter_var($request->is_free, FILTER_VALIDATE_BOOLEAN);
        if ($isFree) {
            $hasDiscount = false;
            $discountPercentage = 0;
            $originalPrice = 0.0;
            $discountPrice = 0.0;
            $effectivePrice = 0.0;
        } else {
            $hasDiscount = filter_var($request->has_discount, FILTER_VALIDATE_BOOLEAN);
            $originalPrice = $request->original_price !== null ? (float)$request->original_price : (float)($request->price ?? 0);
            $discountPrice = $request->discount_price !== null ? (float)$request->discount_price : $originalPrice;
            
            if ($hasDiscount && $originalPrice > 0 && $discountPrice < $originalPrice) {
                $discountPercentage = (int) round((($originalPrice - $discountPrice) / $originalPrice) * 100);
                $effectivePrice = $discountPrice;
            } else {
                $hasDiscount = false;
                $discountPercentage = 0;
                $effectivePrice = $originalPrice > 0 ? $originalPrice : (float)($request->price ?? 0);
            }
        }

        $parseJson = function ($val) {
            if (empty($val)) return null;
            if (is_array($val)) return $val;
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return is_array($decoded) ? $decoded : null;
            }
            return null;
        };

        $prices = $parseJson($request->prices);
        $originalPrices = $parseJson($request->original_prices);
        $discountPrices = $parseJson($request->discount_prices);

        $plan = CoursePlan::create([
            'course_id' => $courseId,
            'name' => $request->name,
            'name_en' => $request->name_en,
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath,
            'is_free' => $isFree,
            'price' => $effectivePrice,
            'original_price' => $originalPrice,
            'discount_price' => $discountPrice,
            'has_discount' => $hasDiscount,
            'discount_percentage' => $discountPercentage,
            'currency' => $request->currency ?: 'EGP',
            'prices' => $prices,
            'original_prices' => $originalPrices,
            'discount_prices' => $discountPrices,
            'duration_days' => $request->duration_days ?: 30,
            'duration_type' => $request->duration_type ?: 'days',
            'is_active' => filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => $request->sort_order ?? 0,
            'features' => $parseJson($request->features),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة الباقة بنجاح',
            'data' => $plan
        ]);
    }

    public function updateCoursePlan(Request $request, $id)
    {
        $plan = CoursePlan::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:course_plans,slug,' . $id . ',id',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric',
            'original_price' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'currency' => 'sometimes|string|max:3',
            'duration_days' => 'sometimes|integer|min:1',
            'duration_type' => 'sometimes|string|in:days,months,years',
            'is_active' => 'sometimes',
            'sort_order' => 'sometimes|integer',
            'features' => 'nullable',
            'image' => 'nullable',
        ]);

        if ($request->has('name')) $plan->name = $request->name;
        if ($request->has('name_en')) $plan->name_en = $request->name_en;
        if ($request->has('slug')) $plan->slug = $request->slug;
        if ($request->has('description')) $plan->description = $request->description;
        if ($request->has('currency')) $plan->currency = $request->currency;
        if ($request->has('duration_days')) $plan->duration_days = $request->duration_days;
        if ($request->has('duration_type')) $plan->duration_type = $request->duration_type;
        if ($request->has('is_active')) $plan->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        if ($request->has('sort_order')) $plan->sort_order = $request->sort_order;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $stored = $file->store('plans', 'public');
            $plan->image = 'storage/' . $stored;
        } elseif ($request->filled('image') && is_string($request->image)) {
            $plan->image = $request->image;
        }

        if ($request->has('is_free')) {
            $plan->is_free = filter_var($request->is_free, FILTER_VALIDATE_BOOLEAN);
        }

        if ($plan->is_free) {
            $plan->price = 0.0;
            $plan->original_price = 0.0;
            $plan->discount_price = 0.0;
            $plan->has_discount = false;
            $plan->discount_percentage = 0;
        } else {
            if ($request->has('has_discount')) {
                $plan->has_discount = filter_var($request->has_discount, FILTER_VALIDATE_BOOLEAN);
            }
            if ($request->has('original_price')) {
                $plan->original_price = $request->original_price !== null ? (float)$request->original_price : null;
            }
            if ($request->has('discount_price')) {
                $plan->discount_price = $request->discount_price !== null ? (float)$request->discount_price : null;
            }

            $orig = $plan->original_price ?? (float)$plan->price;
            $disc = $plan->discount_price ?? $orig;
            if ($plan->has_discount && $orig > 0 && $disc < $orig) {
                $plan->discount_percentage = (int) round((($orig - $disc) / $orig) * 100);
                $plan->price = $disc;
            } else {
                $plan->has_discount = false;
                $plan->discount_percentage = 0;
                if ($request->has('price') && $request->price !== null) {
                    $plan->price = (float)$request->price;
                } elseif ($orig > 0) {
                    $plan->price = $orig;
                }
            }
        }

        $parseJson = function ($val) {
            if (empty($val)) return null;
            if (is_array($val)) return $val;
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return is_array($decoded) ? $decoded : null;
            }
            return null;
        };

        if ($request->has('prices')) $plan->prices = $parseJson($request->prices);
        if ($request->has('original_prices')) $plan->original_prices = $parseJson($request->original_prices);
        if ($request->has('discount_prices')) $plan->discount_prices = $parseJson($request->discount_prices);
        if ($request->has('features')) $plan->features = $parseJson($request->features);

        $plan->save();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الباقة بنجاح',
            'data' => $plan
        ]);
    }

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

    // 11 إدارة ربط المحاضرات بالباقات (Plan Lessons)
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
            'message' => 'تم ربط المحاضرة باقة بنجاح',
            'data' => $planLesson
        ]);
    }

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