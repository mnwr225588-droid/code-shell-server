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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminContentController extends Controller
{
    public function getCourses(Request $request)
    {
        if ($request->boolean('summary')) {
            $courses = \Cache::remember('admin_course_summaries_v1', 30, function () {
                return DB::table('courses')
                    ->select(['id', 'title', 'thumbnail', 'is_free', 'is_coming_soon'])
                    ->orderBy('id', 'asc')
                    ->get()
                    ->map(static fn ($course) => (array) $course)
                    ->all();
            });

            return response()->json([
                'status' => true,
                'data'   => $courses,
            ])->header('Cache-Control', 'no-store, private');
        }

        $courses = Course::with('category')->orderBy('id', 'asc')->get();
        $courseIds = $courses->pluck('id');

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
        ])->header('Cache-Control', 'no-store, private');
    }

    public function getCourseForAdminEdit($id)
    {
        $course = Course::query()
            ->select([
                'id',
                'category_id',
                'title',
                'description',
                'what_will_learn',
                'thumbnail',
                'is_free',
                'is_coming_soon',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $course->id,
                'category_id' => $course->category_id,
                'title' => $course->title,
                'description' => $course->description,
                'what_will_learn' => $course->what_will_learn,
                'thumbnail' => $course->thumbnail,
                'thumbnail_url' => $course->thumbnail_url,
                'is_free' => $course->is_free,
                'is_coming_soon' => $course->is_coming_soon,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

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

    public function storeCourse(Request $request)
    {
        $imageField = $request->file('image_path') !== null ? 'image_path' : 'thumbnail';
        $imageRule = $request->file('image_path') !== null || $request->file('thumbnail') !== null
            ? 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240'
            : 'nullable|string';

        $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'what_you_will_learn' => 'nullable|string',
            'thumbnail'      => $imageRule,
            'image_path'     => $imageRule,
            'is_free'        => 'required|boolean',
            'price'          => 'nullable|numeric',
            'original_price' => 'nullable|numeric',
            'has_discount'   => 'nullable|boolean',
            'is_coming_soon' => 'boolean',
            'prices'         => 'nullable',
            'prices.*'       => 'nullable|numeric',
        ]);

        $thumbnailPath = null;
        if ($request->hasFile($imageField)) {
            $thumbnailPath = $this->storeImageOnR2($request->file($imageField), 'courses/thumbnails');
        } elseif ($request->filled('image_path') && is_string($request->image_path)) {
            $thumbnailPath = $request->image_path;
        } elseif ($request->filled('thumbnail') && is_string($request->thumbnail)) {
            $thumbnailPath = $request->thumbnail;
        }

        $prices = PricingService::normalizePrices($request->input('prices'));

        $discountPercentage = null;
        $hasDiscount = $request->has_discount ?? false;
        if ($hasDiscount && $request->original_prices && $request->discount_prices) {
            $originalPrices = PricingService::normalizePrices($request->input('original_prices'));
            $discountPrices = PricingService::normalizePrices($request->input('discount_prices'));
            
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
                'what_will_learn' => $request->filled('what_you_will_learn')
                    ? $this->parseLearningPoints($request->what_you_will_learn)
                    : null,
                'thumbnail'      => $thumbnailPath,
                'is_free'        => $request->is_free,
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
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'يوجد كورس بنفس العنوان بالفعل.',
                ], 422);
            }
            throw $e;
        }

        $this->invalidateCourseContentCache();

        return response()->json([
            'status'  => true,
            'message' => 'تمت إضافة الكورس بنجاح',
            'data'    => $course
        ]);
    }

    public function updateCourse(Request $request, $id)
    {
        $course = Course::findCourseSafely($id);
        $imageRule = $request->file('image_path') !== null || $request->file('thumbnail') !== null
            ? 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240'
            : 'nullable|string';

        $request->validate([
            'category_id'    => 'sometimes|exists:categories,id',
            'title'          => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'what_you_will_learn' => 'sometimes|nullable|string',
            'thumbnail'      => $imageRule,
            'image_path'     => $imageRule,
            'is_free'        => 'sometimes|boolean',
            'is_active'      => 'sometimes|boolean',
            'is_coming_soon' => 'sometimes|boolean',
            'price'          => 'sometimes|numeric',
            'original_price' => 'sometimes|numeric',
            'has_discount'   => 'sometimes|boolean',
            'prices'         => 'nullable',
            'prices.*'       => 'nullable|numeric',
        ]);

        if ($request->has('category_id')) $course->category_id = $request->category_id;
        if ($request->has('title')) $course->title = $request->title;
        if ($request->has('description')) $course->description = $request->description;
        if ($request->has('what_you_will_learn')) {
            $course->what_will_learn = $this->parseLearningPoints($request->what_you_will_learn);
        }
        if ($request->has('is_free')) $course->is_free = $request->is_free;
        if ($request->has('is_active')) $course->is_active = $request->is_active;
        if ($request->has('is_coming_soon')) $course->is_coming_soon = $request->is_coming_soon;
        if ($request->has('price')) $course->price = $request->price;
        if ($request->has('original_price')) $course->original_price = $request->original_price;
        if ($request->has('has_discount')) $course->has_discount = $request->has_discount;
        $imageField = $request->file('image_path') !== null ? 'image_path' : 'thumbnail';
        if ($request->hasFile($imageField)) {
            $course->thumbnail = $this->storeImageOnR2($request->file($imageField), 'courses/thumbnails');
        } elseif ($request->filled('image_path') && is_string($request->image_path)) {
            $course->thumbnail = $request->image_path;
        } elseif ($request->filled('thumbnail') && is_string($request->thumbnail)) {
            $course->thumbnail = $request->thumbnail;
        }
        
        if ($request->has('has_discount') && $request->has('original_prices') && $request->has('discount_prices')) {
            if ($request->has_discount) {
                $originalPrices = PricingService::normalizePrices($request->input('original_prices'));
                $discountPrices = PricingService::normalizePrices($request->input('discount_prices'));
                
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
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'يوجد كورس آخر بنفس العنوان.',
                ], 422);
            }
            throw $e;
        }

        $this->invalidateCourseContentCache();

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث الكورس بنجاح',
            'data'    => [
                'id' => $course->id,
                'title' => $course->title,
            ],
        ]);
    }

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
            }
            
            $isOptional = filter_var($request->is_optional, FILTER_VALIDATE_BOOLEAN);

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

            $isOptional = filter_var($request->is_optional, FILTER_VALIDATE_BOOLEAN);

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

    public function getUploadTasks(Request $request)
    {
        $tasks = UploadTask::with('admin')->orderBy('created_at', 'desc')->get();
        return response()->json(['status' => true, 'data' => $tasks]);
    }

    public function deleteUploadTask($id)
    {
        $task = UploadTask::find($id);
        if (!$task) {
            return response()->json(['status' => false, 'message' => 'مهمة الرفع غير موجودة'], 404);
        }

        if ($task->upload_id) {
            $disk = Storage::disk('local');
            try { $disk->deleteDirectory('chunks/' . $task->upload_id); } catch (\Throwable $e) {}
        }

        $task->delete();
        return response()->json(['status' => true, 'message' => 'تم حذف مهمة الرفع بنجاح']);
    }

    public function getUsers(Request $request)
    {
        $users = User::select('id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'birth_date', 'created_at')->get();
        return response()->json(['status' => true, 'data' => $users]);
    }

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

        return response()->json(['status' => true, 'data' => $userData]);
    }

    public function togglePublish($id)
    {
        $course = Course::findCourseSafely($id);
        $wasComingSoon = (bool) $course->is_coming_soon;

        $course->is_coming_soon = !$course->is_coming_soon;
        $course->save();
        $this->invalidateCourseContentCache();

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
            $userIds = DB::table('course_reservations')
                ->where('course_id', $course->id)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($userIds)) return;

            $title = 'الكورس أصبح متاحًا 🎉';
            $body = "الكورس '{$course->title}' الذي قمت بحجزه أصبح متاحًا الآن.";

            \App\Jobs\SendPushNotificationJob::dispatch(
                $userIds, $title, $body,
                ['type' => 'course_available', 'course_id' => (string) $course->id],
                null, $course->id, 'course_available', null
            );
        } catch (\Throwable $e) {
            Log::error('notifyCoursePublished error: ' . $e->getMessage());
        }
    }

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

            return response()->json(['status' => true, 'message' => 'تم حذف الدرس وملفاته بنجاح']);
        } catch (\Exception $e) {
            Log::error('Error deleting lesson #' . $id . ': ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل في حذف الدرس: ' . $e->getMessage()], 500);
        }
    }

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
            return response()->json(['status' => true, 'message' => 'تم حذف المستوى ومحتوياته بالكامل']);
        } catch (\Exception $e) {
            Log::error('Error deleting level #' . $id . ': ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل في حذف المستوى: ' . $e->getMessage()], 500);
        }
    }

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
            return response()->json(['status' => true, 'message' => 'تم حذف الحساب نهائياً بنجاح']);
        } catch (\Exception $e) {
            Log::error('Error deleting user #' . $id . ': ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل في حذف الحساب: ' . $e->getMessage()], 500);
        }
    }

    public function getCoursePlans($courseId)
    {
        $course = Course::findCourseSafely($courseId);
        $plans = CoursePlan::where('course_id', $courseId)
            ->withCount(['levels', 'groups', 'onlineLectures'])
            ->orderBy('id', 'asc')
            ->get();
        return response()->json(['status' => true, 'data' => $plans])
            ->header('Cache-Control', 'no-store, private');
    }

    public function storeCoursePlan(Request $request, $courseId)
    {
        $course = Course::findCourseSafely($courseId);
        $imageRule = $request->file('image') !== null
            ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
            : 'nullable|string';
        $imagePathRule = $request->file('image_path') !== null
            ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
            : 'nullable|string';

        $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'what_you_will_learn' => 'nullable|string',
            'price' => 'nullable|numeric',
            'original_price' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'currency' => 'sometimes|string|max:3',
            'duration_days' => 'sometimes|integer|min:1',
            'duration_type' => 'sometimes|string|in:days,months,years',
            'is_active' => 'sometimes',
            'sort_order' => 'sometimes|integer',
            'features' => 'nullable',
            'image' => $imageRule,
            'image_path' => $imagePathRule,
        ]);

        $slug = $request->slug;
        if (empty($slug)) {
            $slug = Str::slug($request->name ?: 'plan') . '-' . substr(uniqid(), -6);
        }
        while (CoursePlan::where('course_id', $courseId)->where('slug', $slug)->exists()) {
            $slug = Str::slug($request->name ?: 'plan') . '-' . rand(1000, 9999);
        }

        $imageField = $request->hasFile('image_path') ? 'image_path' : 'image';
        $imagePath = null;
        if ($request->hasFile($imageField)) {
            $file = $request->file($imageField);
            $imagePath = $this->storeImageOnR2($file, 'plans');
        } elseif ($request->filled('image_path') && is_string($request->image_path)) {
            $imagePath = $request->image_path;
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
        $currencyCode = strtoupper((string) ($request->currency ?: 'EGP'));
        $prices = $this->setCurrencyPrice($prices, $currencyCode, $effectivePrice);
        $originalPrices = $this->setCurrencyPrice($originalPrices, $currencyCode, $originalPrice);
        $discountPrices = $this->setCurrencyPrice($discountPrices, $currencyCode, $discountPrice);

        $plan = CoursePlan::create([
            'course_id' => $courseId,
            'name' => $request->name,
            'name_en' => $request->name_en,
            'slug' => $slug,
            'description' => $request->description,
            'what_you_will_learn' => $request->what_you_will_learn,
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

        $this->invalidateCourseContentCache();

        return response()->json(['status' => true, 'message' => 'تمت إضافة الباقة بنجاح', 'data' => $plan]);
    }

    public function updateCoursePlan(Request $request, $id)
    {
        $plan = CoursePlan::findOrFail($id);
        $imageRule = $request->file('image') !== null
            ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
            : 'nullable|string';
        $imagePathRule = $request->file('image_path') !== null
            ? 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240'
            : 'nullable|string';

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:course_plans,slug,' . $id . ',id',
            'description' => 'nullable|string',
            'what_you_will_learn' => 'sometimes|nullable|string',
            'price' => 'nullable|numeric',
            'original_price' => 'nullable|numeric',
            'discount_price' => 'nullable|numeric',
            'currency' => 'sometimes|string|max:3',
            'duration_days' => 'sometimes|integer|min:1',
            'duration_type' => 'sometimes|string|in:days,months,years',
            'is_active' => 'sometimes',
            'sort_order' => 'sometimes|integer',
            'features' => 'nullable',
            'image' => $imageRule,
            'image_path' => $imagePathRule,
        ]);

        if ($request->has('name')) $plan->name = $request->name;
        if ($request->has('name_en')) $plan->name_en = $request->name_en;
        if ($request->has('slug')) $plan->slug = $request->slug;
        if ($request->has('description')) $plan->description = $request->description;
        if ($request->has('what_you_will_learn')) $plan->what_you_will_learn = $request->what_you_will_learn;
        if ($request->has('currency')) $plan->currency = $request->currency;
        if ($request->has('duration_days')) $plan->duration_days = $request->duration_days;
        if ($request->has('duration_type')) $plan->duration_type = $request->duration_type;
        if ($request->has('is_active')) $plan->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        if ($request->has('sort_order')) $plan->sort_order = $request->sort_order;

        $oldImagePathToDelete = null;
        $oldImageDisk = 'public';
        $imageField = $request->hasFile('image_path') ? 'image_path' : 'image';
        if ($request->hasFile($imageField)) {
            $file = $request->file($imageField);
            $stored = $this->storeImageOnR2($file, 'plans');
            $oldImagePathToDelete = ltrim((string) $plan->image, '/');
            if (str_starts_with($oldImagePathToDelete, 'r2/')) {
                $oldImageDisk = 'r2';
                $oldImagePathToDelete = substr($oldImagePathToDelete, strlen('r2/'));
            } elseif (str_starts_with($oldImagePathToDelete, 'storage/')) {
                $oldImagePathToDelete = substr($oldImagePathToDelete, strlen('storage/'));
            }
            $plan->image = $stored;
        } elseif ($request->filled('image_path') && is_string($request->image_path)) {
            $plan->image = $request->image_path;
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

        $currencyCode = strtoupper((string) ($plan->currency ?: 'EGP'));
        $plan->prices = $this->setCurrencyPrice($plan->prices, $currencyCode, (float) $plan->price);
        $plan->original_prices = $this->setCurrencyPrice($plan->original_prices, $currencyCode, (float) ($plan->original_price ?? $plan->price));
        $plan->discount_prices = $this->setCurrencyPrice($plan->discount_prices, $currencyCode, (float) ($plan->discount_price ?? $plan->price));

        $plan->save();

        if ($oldImagePathToDelete !== null && $oldImagePathToDelete !== '' && !preg_match('#^https?://#i', $oldImagePathToDelete) && !str_starts_with($oldImagePathToDelete, 'assets/') && Storage::disk($oldImageDisk)->exists($oldImagePathToDelete)) {
            Storage::disk($oldImageDisk)->delete($oldImagePathToDelete);
        }

        $this->invalidateCourseContentCache();

        return response()->json(['status' => true, 'message' => 'تم تحديث الباقة بنجاح', 'data' => $plan]);
    }

    public function deleteCoursePlan($id)
    {
        try {
            $plan = CoursePlan::findOrFail($id);
            $plan->delete();
            $this->invalidateCourseContentCache();
            return response()->json(['status' => true, 'message' => 'تم حذف الباقة بنجاح']);
        } catch (\Exception $e) {
            Log::error('Error deleting plan #' . $id . ': ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل في حذف الباقة: ' . $e->getMessage()], 500);
        }
    }

    public function getPlanLessons($planId)
    {
        $plan = CoursePlan::findOrFail($planId);
        $planLessons = PlanLesson::where('plan_id', $planId)
            ->with(['lesson', 'level', 'group'])
            ->orderBy('id', 'asc')
            ->get();
        return response()->json(['status' => true, 'data' => $planLessons]);
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

        return response()->json(['status' => true, 'message' => 'تم ربط المحاضرة باقة بنجاح', 'data' => $planLesson]);
    }

    public function removeLessonFromPlan($id)
    {
        try {
            $planLesson = PlanLesson::findOrFail($id);
            $planLesson->delete();
            return response()->json(['status' => true, 'message' => 'تم إزالة المحاضرة من الباقة بنجاح']);
        } catch (\Exception $e) {
            Log::error('Error removing lesson from plan #' . $id . ': ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'فشل في إزالة المحاضرة: ' . $e->getMessage()], 500);
        }
    }

    private function invalidateCourseContentCache(): void
    {
        \Cache::forever('courses_index_cache_version', (string) Str::uuid());
        \Cache::forget('admin_course_summaries_v1');
        \Cache::forget('admin_all_courses_v2');
        \Cache::forget('all_courses_v2');
    }

    private function setCurrencyPrice(?array $prices, string $currencyCode, float $price): array
    {
        $prices = $prices ?? [];
        $prices[$currencyCode] = $price;

        return $prices;
    }

    private function storeImageOnR2(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'r2');
        if (!$path) {
            throw new \RuntimeException('تعذر حفظ الصورة في التخزين الدائم.');
        }

        return 'r2/' . $path;
    }

    private function parseLearningPoints(?string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text ?? '');

        return array_values(array_filter(array_map('trim', $lines ?: [])));
    }
}