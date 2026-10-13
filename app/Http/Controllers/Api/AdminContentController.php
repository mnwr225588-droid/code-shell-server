<?php

namespace App\Http\Controllers\Api;

/// Ø§Ù„Ù€ AdminContentController
/// Ø¯Ù‡ Ø§Ù„Ø¹ØµØ¨ Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠ Ù„ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø¥Ø¯Ø§Ø±Ø© (code_shell_admin).
/// Ù…Ø³Ø¤ÙˆÙ„ Ø¹Ù† Ø¥Ø¶Ø§ÙØ©/ØªØ¹Ø¯ÙŠÙ„/Ø­Ø°Ù: Ø§Ù„Ø£Ù‚Ø³Ø§Ù…ØŒ Ø§Ù„ÙƒÙˆØ±Ø³Ø§ØªØŒ Ø§Ù„Ù…Ø³ØªÙˆÙŠØ§ØªØŒ ÙˆØ§Ù„Ø¯Ø±ÙˆØ³.
/// ÙˆÙƒÙ…Ø§Ù† Ø¨ÙŠØ¹Ø±Ø¶ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† ÙˆØ§Ù„Ø­Ø¬ÙˆØ²Ø§Øª.
/// âš ï¸ Ù…Ù‡Ù…: ÙƒÙ„ Ø§Ù„Ù€ Routes Ø§Ù„Ù„ÙŠ Ø¨ØªØ´Ø§ÙˆØ± Ø¹Ù„Ù‰ Ø§Ù„Ù€ Controller Ø¯Ù‡ Ù…Ø­Ù…ÙŠØ© Ø¨Ù€ auth:sanctum ÙÙŠ routes/api.php
/// ÙˆØ£ÙŠ ØªØ¹Ø¯ÙŠÙ„ Ù‡Ù†Ø§ Ù‡ÙŠØ³Ù…Ù‘Ø¹ ÙÙŠ Ø§Ù„Ù€ Providers ÙˆØ§Ù„Ù€ Services ÙÙŠ ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø£Ø¯Ù…Ù† (Flutter).

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
    // 0ï¸âƒ£ Ø¬Ù„Ø¨ Ø¬Ù…ÙŠØ¹ Ø§Ù„ÙƒÙˆØ±Ø³Ø§Øª (Ø¨Ù…Ø§ ÙÙŠÙ‡Ø§ ØºÙŠØ± Ø§Ù„Ù…Ù†Ø´ÙˆØ±Ø©)
    public function getCourses(Request $request)
    {
        // âš¡ ÙƒØ§Ø´ 5 Ø¯Ù‚Ø§Ø¦Ù‚ Ù„ØªØ³Ø±ÙŠØ¹ ØªØ­Ù…ÙŠÙ„ Ù„ÙˆØ­Ø© Ø§Ù„Ø£Ø¯Ù…Ù†
        return \Cache::remember('admin_all_courses_v2', 300, function () {
            // Admin needs to see all courses to manage them
            $courses = Course::with('category')->orderBy('id', 'desc')->get();
            $courseIds = $courses->pluck('id');

            // âš¡ Ø§Ø³ØªØ¹Ù„Ø§Ù…Ø§Øª Ù…Ø¬Ù…Ø¹Ø© ØªÙ…Ù†Ø¹ Ø§Ø³ØªØ¹Ù„Ø§Ù…Ø§Øª Ø§Ù„Ù€ Accessors Ù„ÙƒÙ„ ÙƒÙˆØ±Ø³ (ÙƒØ§Ù†Øª Ø³Ø¨Ø¨ 16 Ø«Ø§Ù†ÙŠØ©)
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

    // 1ï¸âƒ£ Ø¥Ø¶Ø§ÙØ©/ØªØ­Ø¯ÙŠØ« Ù‚Ø³Ù… Ø£Ùˆ Ù„ØºØ© Ø¨Ø±Ù…Ø¬Ø© Ù…Ø¹ Ø§Ù„Ø£ÙŠÙ‚ÙˆÙ†Ø©
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
            'message' => 'ØªÙ…Øª Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù‚Ø³Ù… Ø¨Ù†Ø¬Ø§Ø­',
            'data'    => $category
        ]);
    }

    // 2ï¸âƒ£ Ø¥Ø¶Ø§ÙØ© ÙƒÙˆØ±Ø³ Ù…Ø¹ Ø§Ù„ØºÙ„Ø§Ù ÙˆØ§Ù„Ù†ÙˆØ¹ (Ù…Ø¬Ø§Ù†ÙŠ/Ù…Ø¯ÙÙˆØ¹/Ù‚Ø±ÙŠØ¨Ø§Ù‹)
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

        // Ø­Ø³Ø§Ø¨ Ù†Ø³Ø¨Ø© Ø§Ù„ØªØ®ÙÙŠØ¶ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹
        $discountPercentage = null;
        $hasDiscount = $request->has_discount ?? false;
        if ($hasDiscount && $request->original_prices && $request->discount_prices) {
            $originalPrices = PricingService::normalizePrices($request->input('original_prices'));
            $discountPrices = PricingService::normalizePrices($request->input('discount_prices'));
            
            // Ø­Ø³Ø§Ø¨ Ù…ØªÙˆØ³Ø· Ù†Ø³Ø¨Ø© Ø§Ù„ØªØ®ÙÙŠØ¶ Ù…Ù† Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø¹Ù…Ù„Ø§Øª
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
                // Ø§Ù„Ø¹Ù…ÙˆØ¯ Ø§Ù„Ù‚Ø¯ÙŠÙ… Ù„Ù„ØªÙˆØ§ÙÙ‚Ø› Ø§Ù„Ù…ØµØ¯Ø± Ø§Ù„Ø­Ù‚ÙŠÙ‚ÙŠ Ù‡Ùˆ Ù…ØµÙÙˆÙØ© prices (EGP Ø§ÙØªØ±Ø§Ø¶ÙŠØ§Ù‹).
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
            // Ù‚ÙŠØ¯ Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ÙØ±ÙŠØ¯: Ù…Ù†Ø¹ Ø¥Ù†Ø´Ø§Ø¡ ÙƒÙˆØ±Ø³Ø§Øª Ø¨Ù†ÙØ³ Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø¨Ø¯Ù„ 500 ØºØ§Ù…Ø¶Ø©
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'ÙŠÙˆØ¬Ø¯ ÙƒÙˆØ±Ø³ Ø¨Ù†ÙØ³ Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø¨Ø§Ù„ÙØ¹Ù„. ÙŠØ±Ø¬Ù‰ Ø§Ø³ØªØ®Ø¯Ø§Ù… Ø¹Ù†ÙˆØ§Ù† Ù…Ø®ØªÙ„Ù Ø£Ùˆ ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙƒÙˆØ±Ø³ Ø§Ù„Ù…ÙˆØ¬ÙˆØ¯.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'status'  => true,
            'message' => 'ØªÙ…Øª Ø¥Ø¶Ø§ÙØ© Ø§Ù„ÙƒÙˆØ±Ø³ Ø¨Ù†Ø¬Ø§Ø­',
            'data'    => $course
        ]);
    }

    // 2ï¸âƒ£.Ø¨ ØªØ¹Ø¯ÙŠÙ„ ÙƒÙˆØ±Ø³ (Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ© + Ø§Ù„Ø£Ø³Ø¹Ø§Ø± Ù…ØªØ¹Ø¯Ø¯Ø© Ø§Ù„Ø¹Ù…Ù„Ø§Øª)
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
        // Ø­Ø³Ø§Ø¨ Ù†Ø³Ø¨Ø© Ø§Ù„ØªØ®ÙÙŠØ¶ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¹Ù†Ø¯ Ø§Ù„ØªØ¹Ø¯ÙŠÙ„
        if ($request->has('has_discount') && $request->has('original_prices') && $request->has('discount_prices')) {
            if ($request->has_discount) {
                $originalPrices = PricingService::normalizePrices($request->input('original_prices'));
                $discountPrices = PricingService::normalizePrices($request->input('discount_prices'));
                
                // Ø­Ø³Ø§Ø¨ Ù…ØªÙˆØ³Ø· Ù†Ø³Ø¨Ø© Ø§Ù„ØªØ®ÙÙŠØ¶ Ù…Ù† Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø¹Ù…Ù„Ø§Øª
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
            // Ù‚ÙŠØ¯ Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ÙØ±ÙŠØ¯: Ø±Ø³Ø§Ù„Ø© ÙˆØ§Ø¶Ø­Ø© Ø¨Ø¯Ù„ Ø®Ø·Ø£ Ø³ÙŠØ±ÙØ± ØºØ§Ù…Ø¶
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'status'  => false,
                    'message' => 'ÙŠÙˆØ¬Ø¯ ÙƒÙˆØ±Ø³ Ø¢Ø®Ø± Ø¨Ù†ÙØ³ Ø§Ù„Ø¹Ù†ÙˆØ§Ù†. ÙŠØ±Ø¬Ù‰ Ø§Ø³ØªØ®Ø¯Ø§Ù… Ø¹Ù†ÙˆØ§Ù† Ù…Ø®ØªÙ„Ù.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'status'  => true,
            'message' => 'ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„ÙƒÙˆØ±Ø³ Ø¨Ù†Ø¬Ø§Ø­',
            'data'    => $course
        ]);
    }

    // 3ï¸âƒ£ Ø¥Ø¶Ø§ÙØ© Ù…Ø³ØªÙˆÙ‰ Ø¯Ø§Ø®Ù„ ÙƒÙˆØ±Ø³ Ø£Ùˆ Ø¨Ø§Ù‚Ø©
    public function storeLevel(Request $request)
    {
        $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'plan_id'     => 'nullable|exists:course_plans,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'what_you_will_learn' => 'nullable|string',
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
            'message' => 'ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ù…Ø³ØªÙˆÙ‰ Ø¨Ù†Ø¬Ø§Ø­',
            'data'    => $level
        ]);
    }

    // 4ï¸âƒ£ Ø¥Ø¶Ø§ÙØ© Ø¯Ø±Ø³ + ÙÙŠØ¯ÙŠÙˆ + Ø£Ø³Ø¦Ù„Ø© Ø§Ù„Ø§Ø®ØªØ¨Ø§Ø± Ø§Ù„ØªØ§Ø¨Ø¹ Ù„Ù‡
    /// âš ï¸ Flow Ø§Ù„Ø±ÙØ¹ (Upload Flow) Ù„Ù„ÙÙŠØ¯ÙŠÙˆÙ‡Ø§Øª:
    /// Ø§Ù„Ø¯Ø±Ø³ Ù…Ù…ÙƒÙ† ÙŠØ³ØªÙ‚Ø¨Ù„ Ø±Ø§Ø¨Ø· (URL) Ø£Ùˆ Ù…Ù„Ù ÙÙŠØ¯ÙŠÙˆ Ø­Ù‚ÙŠÙ‚ÙŠ.
    /// Ù„Ùˆ Ù…Ù„Ù:
    /// 1. Ø¨ÙŠØ±ÙØ¹ Ø§Ù„Ù…Ù„Ù Ø¹Ù„Ù‰ Cloudflare R2 (Storage: disk('r2')).
    /// 2. Ø¨ÙŠØ³ØªØ®Ø¯Ù… `VideoProcessor` Ø¹Ù„Ø´Ø§Ù† ÙŠØ¹Ù…Ù„ Fast-Start (ÙŠÙ†Ù‚Ù„ Ø§Ù„Ù€ moov atom Ù„Ø£ÙˆÙ„ Ø§Ù„Ù…Ù„Ù)
    ///    Ø¯Ù‡ Ø¨ÙŠØ®Ù„ÙŠ ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø·Ø§Ù„Ø¨ ÙŠÙ‚Ø¯Ø± ÙŠØ¹Ù…Ù„ Stream Ù„Ù„ÙÙŠØ¯ÙŠÙˆ ÙÙˆØ±Ø§Ù‹ Ù…Ù† ØºÙŠØ± Ù…Ø§ ÙŠØ³ØªÙ†Ù‰ ØªØ­Ù…ÙŠÙ„Ù‡ Ø¨Ø§Ù„ÙƒØ§Ù…Ù„.
    /// 3. Ø¨ÙŠØ³Ø¬Ù„ Ø§Ù„Ø£Ø³Ø¦Ù„Ø© (Quiz) Ø§Ù„ØªØ§Ø¨Ø¹Ø© Ù„Ù„Ø¯Ø±Ø³ ÙÙŠ Ù†ÙØ³ Ø§Ù„Ù€ Request Ø¹Ù„Ø´Ø§Ù† ÙŠÙ‚Ù„Ù„ Ø§Ù„Ù€ API calls.
    /// 4. Ø¨ÙŠØ¨Ø¹Øª Firebase Notification ØµØ§Ù…Øª (Silent Push) Ø¹Ù„Ø´Ø§Ù† ÙŠØ®Ù„ÙŠ Ø£Ø¬Ù‡Ø²Ø© Ø§Ù„Ø·Ù„Ø§Ø¨
    ///    ØªØ¹Ù…Ù„ Refresh Ù„Ù„Ù…Ø­ØªÙˆÙ‰ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¨Ø¯ÙˆÙ† Ù…Ø§ Ø§Ù„ÙŠÙˆØ²Ø± ÙŠØ¹Ù…Ù„ Pull to refresh.
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
            // Ø±ÙØ¹ Ù…Ù„Ù Ø§Ù„ÙÙŠØ¯ÙŠÙˆ Ø¥Ù† ÙˆØ¬Ø¯ Ø£Ùˆ Ø£Ø®Ø° Ø§Ù„Ø±Ø§Ø¨Ø·
            $videoPath = null;
            if ($request->hasFile('video')) {
                $file = $request->file('video');
                $key = $file->store('lessons/videos', 'r2');
                Log::info('Video uploaded to R2: ' . $key);

                // ØªØ­ÙˆÙŠÙ„ MP4 Ø¥Ù„Ù‰ Fast-Start (moov ÙÙŠ Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©) Ø­ØªÙ‰ Ù„Ø§ ÙŠØªÙˆÙ‚Ù
                // Ø§Ù„Ø¨Ø« Ø¨Ø¹Ø¯ Ø«ÙˆØ§Ù†Ù Ù…Ù† Ø§Ù„ØªØ´ØºÙŠÙ„ â€” Ø¨Ø¯ÙˆÙ† Ø¥Ø¹Ø§Ø¯Ø© ØªØ±Ù…ÙŠØ² (Ù„Ø§ ÙÙ‚Ø¯ Ø¬ÙˆØ¯Ø©).
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

            // Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ø£Ø³Ø¦Ù„Ø© Ø¥Ù† ÙˆØ¬Ø¯Øª Ù…Ø¹ Ø§Ù„Ø¯Ø±Ø³ Ø¨Ø¯Ø§Ø®Ù„ Ù†ÙØ³ Ø§Ù„Ù†Ù…ÙˆØ°Ø¬
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
                'message' => 'ØªÙ… Ø­ÙØ¸ Ø§Ù„Ø¯Ø±Ø³ Ù…Ø¹ Ø§Ù„ÙÙŠØ¯ÙŠÙˆ ÙˆØ§Ù„Ø£Ø³Ø¦Ù„Ø© Ø¨Ù†Ø¬Ø§Ø­',
                'data'    => $lesson->load('questions.options')
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating lesson: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'ÙØ´Ù„ ÙÙŠ Ø­ÙØ¸ Ø§Ù„Ø¯Ø±Ø³: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 4ï¸âƒ£.Ø¨ Ø±ÙØ¹ Ù…Ù‚Ø·Ø¹ ÙˆØ§Ø­Ø¯ Ù…Ù† Ø§Ù„ÙÙŠØ¯ÙŠÙˆ (Chunked Upload for Lessons):
    // ÙŠØ±ÙØ¹ ØªØ·Ø¨ÙŠÙ‚ Ø§Ù„Ø£Ø¯Ù…Ù† Ø§Ù„ÙÙŠØ¯ÙŠÙˆ Ø¹Ù„Ù‰ Ø´ÙƒÙ„ Ù…Ù‚Ø§Ø·Ø¹ ØµØºÙŠØ±Ø© Ù„ØªØ¬Ù†Ø¨ Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰ Ù„Ù„Ø·Ù„Ø¨
    // ÙˆÙŠØ¯Ø¹Ù… Ø§Ø³ØªÙƒÙ…Ø§Ù„ Ø§Ù„Ø±ÙØ¹ Ø¨Ø¹Ø¯ Ø§Ù†Ù‚Ø·Ø§Ø¹ Ø§Ù„Ø§ØªØµØ§Ù„
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
        
        // ØªØ£ÙƒØ¯ Ù…Ù† ÙˆØ¬ÙˆØ¯ Ø§Ù„Ù…Ø¬Ù„Ø¯
        if (!$disk->exists($chunkDir)) {
            $disk->makeDirectory($chunkDir);
        }

        $path = $request->file('data')->storeAs(
            $chunkDir,
            (int) $request->index . '.part',
            'local'
        );

        // ØªØ­Ø¯ÙŠØ« ÙˆÙ‚Øª Ø¢Ø®Ø± Ù†Ø´Ø§Ø· Ù„Ù„Ø±ÙØ¹
        $disk->put($chunkDir . '/.last_activity', now()->toIso8601String());

        // ØªØ­Ø¯ÙŠØ« Ø£Ùˆ Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù‡Ù…Ø© Ø§Ù„Ø±ÙØ¹ ÙÙŠ Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
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

        // ØªØ­Ø¯ÙŠØ« Ø§Ù„ØªÙ‚Ø¯Ù…
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

    // 4ï¸âƒ£.Ø¬ Ø§Ù„ØªØ­Ù‚Ù‚ Ù…Ù† Ø­Ø§Ù„Ø© Ø§Ù„Ø±ÙØ¹ (Upload Status Check):
    // ÙŠØ±Ø¬Ø¹ Ø¹Ø¯Ø¯ Ø§Ù„Ù…Ù‚Ø§Ø·Ø¹ Ø§Ù„Ù…Ø³ØªÙ„Ù…Ø© Ù…Ù† Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù…Ù‚Ø§Ø·Ø¹ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø©
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

        // ØªØ­Ù‚Ù‚ Ù…Ù† Ø¢Ø®Ø± Ù†Ø´Ø§Ø· (Ù„ØªØ­Ø¯ÙŠØ¯ Ø§Ù„Ø±ÙØ¹Ø§Øª Ø§Ù„Ù…Ù†ØªÙ‡ÙŠØ©)
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

    // 4ï¸âƒ£.Ø¯ Ø§Ø³ØªÙƒÙ…Ø§Ù„ Ø±ÙØ¹ Ø¯Ø±Ø³ Ù…Ø¬Ø²Ø£ (Chunked Upload):
    // Ø§Ù„ÙÙŠØ¯ÙŠÙˆ ÙŠÙÙ‚Ø³Ù… ÙÙŠ Ø§Ù„Ù…ØªØµÙØ­ Ù„Ù…Ù‚Ø§Ø·Ø¹ ØµØºÙŠØ±Ø© ØªÙØ±ÙØ¹ Ø¹Ø¨Ø± /admin/upload-lesson-chunkØŒ
    // Ø«Ù… Ù‡Ø°Ø§ Ø§Ù„Ù…Ø³Ø§Ø± ÙŠØ¬Ù…Ø¹Ù‡Ø§ ÙˆÙŠÙƒÙ…Ù„ Ù†ÙØ³ Ù…Ø³Ø§Ø± R2 + Fast-Start Ø§Ù„Ù…ØªØ¨Ø¹ ÙÙŠ storeLessonWithQuiz.
    // ÙŠØªÙŠØ­: Ø§Ù„Ø¥ÙŠÙ‚Ø§Ù Ø§Ù„Ù…Ø¤Ù‚ØªØŒ Ø§Ø³ØªÙƒÙ…Ø§Ù„ Ø§Ù„Ø±ÙØ¹ Ø¨Ø¹Ø¯ Ø§Ù†Ù‚Ø·Ø§Ø¹ Ø§Ù„Ù†Øª Ø£Ùˆ Ø¥Ø¹Ø§Ø¯Ø© ØªØ­Ù…ÙŠÙ„ Ø§Ù„ØµÙØ­Ø©.
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
                'message' => 'ØµÙŠØºØ© Ù…Ù„Ù Ø§Ù„ÙÙŠØ¯ÙŠÙˆ ØºÙŠØ± Ù…Ø¯Ø¹ÙˆÙ…Ø© (Ø§Ù„Ù…Ø³Ù…ÙˆØ­: mp4, mov, avi, mkv, wmv).',
            ], 422);
        }

        $disk = Storage::disk('local');
        $chunkDir = 'chunks/' . $request->upload_id;
        $totalChunks = (int) $request->total_chunks;

        try {
            // 1. Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† ÙˆØµÙˆÙ„ ÙƒÙ„ Ø§Ù„Ù…Ù‚Ø§Ø·Ø¹
            $missing = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!$disk->exists($chunkDir . '/' . $i . '.part')) {
                    $missing[] = $i;
                }
            }
            if (!empty($missing)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Ø¨Ø¹Ø¶ Ù…Ù‚Ø§Ø·Ø¹ Ø§Ù„ÙÙŠØ¯ÙŠÙˆ Ù„Ù… ØªØµÙ„ Ø¨Ø¹Ø¯. Ø£Ø¹Ø¯ Ø§Ù„Ù…Ø­Ø§ÙˆÙ„Ø© Ù„Ø§Ø³ØªÙƒÙ…Ø§Ù„Ù‡Ø§.',
                    'missing' => $missing,
                ], 422);
            }

            // 2. ØªØ¬Ù…ÙŠØ¹ Ø§Ù„Ù…Ù‚Ø§Ø·Ø¹ Ø¨Ø§Ù„ØªØ±ØªÙŠØ¨ ÙÙŠ Ù…Ù„Ù Ù…Ø¤Ù‚Øª
            $mergedTmp = $disk->path($chunkDir . '/merged.tmp');
            $out = @fopen($mergedTmp, 'wb');
            if (!$out) {
                throw new \Exception('ØªØ¹Ø°Ø± Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù„Ù Ù…Ø¤Ù‚Øª Ù„Ù„ØªØ¬Ù…ÙŠØ¹.');
            }
            for ($i = 0; $i < $totalChunks; $i++) {
                $in = @fopen($disk->path($chunkDir . '/' . $i . '.part'), 'rb');
                if (!$in) {
                    fclose($out);
                    throw new \Exception('ØªØ¹Ø°Ø± Ù‚Ø±Ø§Ø¡Ø© Ø§Ù„Ù…Ù‚Ø·Ø¹ Ø±Ù‚Ù… ' . ($i + 1));
                }
                while (!feof($in)) {
                    fwrite($out, fread($in, 1024 * 512));
                }
                fclose($in);
            }
            fclose($out);

            // 3. ØºÙ„Ø§Ù UploadedFile Ù„Ù„Ù…Ø¤Ù‚Øª Ù„ÙŠØºØ°ÙŠ Ù†ÙØ³ Ù…Ø³Ø§Ø± R2 Ø§Ù„Ù…ØªØ¨Ø¹
            // Ù…Ø¹ Ù†ÙˆØ¹ MIME ØµØ­ÙŠØ­ Ø­Ø³Ø¨ Ø§Ù„Ø§Ù…ØªØ¯Ø§Ø¯ Ø§Ù„Ø£ØµÙ„ÙŠ (Ø­ØªÙ‰ Ù„Ø§ ÙŠÙØ®Ù…ÙŽÙ‘Ù† .bin)
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

            // 4. Fast-Start (Ù†Ù‚Ù„ moov Ù„Ø£ÙˆÙ„ Ø§Ù„Ù…Ù„Ù) Ø¨Ø¯ÙˆÙ† Ø¥Ø¹Ø§Ø¯Ø© ØªØ±Ù…ÙŠØ²
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

            // 5. Ø§Ù„ØµÙˆØ±Ø© Ø§Ù„Ù…ØµØºØ±Ø©
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('lessons/thumbnails', 'r2');
                Log::info('Thumbnail uploaded to R2: ' . $thumbnailPath);
            }

            $isOptional = $request->is_optional === 'true' || $request->is_optional === '1' || $request->is_optional === true || $request->is_optional === 1;

            // 6. Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ø¯Ø±Ø³
            $lesson = Lesson::create([
                'level_id'    => $request->level_id,
                'title'       => $request->title,
                'description' => $request->description,
                'video_url'   => $videoPath,
                'thumbnail'   => $thumbnailPath,
                'order_num'   => $request->order_num,
                'is_optional' => $isOptional,
            ]);

            // 7. Ø§Ù„Ø£Ø³Ø¦Ù„Ø© (ØªØµÙ„ ÙƒØ³Ù„Ø³Ù„Ø© JSON Ù…Ù† FormData Ø£Ùˆ Ù…ØµÙÙˆÙØ©)
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

            // 8. ØªØ­Ø¯ÙŠØ« Ù…Ù‡Ù…Ø© Ø§Ù„Ø±ÙØ¹ ÙƒÙ…ÙƒØªÙ…Ù„Ø©
            $uploadTask = UploadTask::where('upload_id', $request->upload_id)->first();
            if ($uploadTask) {
                $uploadTask->status = 'completed';
                $uploadTask->progress = 100;
                $uploadTask->completed_at = now();
                $uploadTask->save();
            }

            // 9. ØªÙ†Ø¸ÙŠÙ Ù…Ø¬Ù„Ø¯ Ø§Ù„Ù…Ù‚Ø§Ø·Ø¹ Ø§Ù„Ù…Ø¤Ù‚Øª
            try { $disk->deleteDirectory($chunkDir); } catch (\Throwable $e) {}

            // 10. Ø¥Ø´Ø¹Ø§Ø± ØµØ§Ù…Øª Ù„Ù„Ø·Ù„Ø§Ø¨ Ø¨ØªØ­Ø¯ÙŠØ« Ø§Ù„Ù…Ø­ØªÙˆÙ‰
            try {
                $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', 'content_updates')
                    ->withData(['action' => 'refresh_content']);
                app('firebase.messaging')->send($message);
            } catch (\Throwable $e) {
                Log::error('FCM update error: ' . $e->getMessage());
            }

            return response()->json([
                'status'  => true,
                'message' => 'ØªÙ… Ø±ÙØ¹ Ø§Ù„Ø¯Ø±Ø³ ÙˆØ­ÙØ¸ Ø§Ù„Ø§Ø®ØªØ¨Ø§Ø±Ø§Øª Ø¨Ù†Ø¬Ø§Ø­',
                'data'    => $lesson->load('questions.options'),
            ]);
        } catch (\Exception $e) {
            Log::error('Chunked lesson completion failed: ' . $e->getMessage());
            
            // ØªØ­Ø¯ÙŠØ« Ù…Ù‡Ù…Ø© Ø§Ù„Ø±ÙØ¹ ÙƒÙØ§Ø´Ù„Ø©
            $uploadTask = UploadTask::where('upload_id', $request->upload_id)->first();
            if ($uploadTask) {
                $uploadTask->status = 'failed';
                $uploadTask->error_message = $e->getMessage();
                $uploadTask->save();
            }
            
            return response()->json([
                'status'  => false,
                'message' => 'ÙØ´Ù„ Ø­ÙØ¸ Ø§Ù„Ø¯Ø±Ø³: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 4ï¸âƒ£.Ù‡ Ø¹Ø±Ø¶ Ø¬Ù…ÙŠØ¹ Ù…Ù‡Ø§Ù… Ø§Ù„Ø±ÙØ¹ (Upload Tasks List)
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

    // 4ï¸âƒ£.Ùˆ Ø­Ø°Ù Ù…Ù‡Ù…Ø© Ø±ÙØ¹ (Delete Upload Task)
    public function deleteUploadTask($id)
    {
        $task = UploadTask::find($id);
        if (!$task) {
            return response()->json([
                'status' => false,
                'message' => 'Ù…Ù‡Ù…Ø© Ø§Ù„Ø±ÙØ¹ ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯Ø©',
            ], 404);
        }

        // ØªÙ†Ø¸ÙŠÙ Ø§Ù„Ù…Ù„ÙØ§Øª Ø§Ù„Ù…Ø¤Ù‚ØªØ© Ø¥Ø°Ø§ ÙˆÙØ¬Ø¯Øª
        if ($task->upload_id) {
            $disk = Storage::disk('local');
            $chunkDir = 'chunks/' . $task->upload_id;
            try { $disk->deleteDirectory($chunkDir); } catch (\Throwable $e) {}
        }

        $task->delete();

        return response()->json([
            'status' => true,
            'message' => 'ØªÙ… Ø­Ø°Ù Ù…Ù‡Ù…Ø© Ø§Ù„Ø±ÙØ¹ Ø¨Ù†Ø¬Ø§Ø­',
        ]);
    }

    // 5ï¸âƒ£ Ø¹Ø±Ø¶ ÙƒØ§ÙØ© Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ† ÙˆØ§Ø´ØªØ±Ø§ÙƒØ§ØªÙ‡Ù… Ø§Ù„Ù…Ø¯ÙÙˆØ¹Ø© ÙÙ‚Ø·
    public function getUsers(Request $request)
    {
        $users = User::select('id', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'birth_date', 'created_at')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $users
        ]);
    }

    // 6ï¸âƒ£ ØªÙØ§ØµÙŠÙ„ Ù…Ø³ØªØ®Ø¯Ù… ÙƒØ§Ù…Ù„Ø© (Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø­Ø³Ø§Ø¨ Ø§Ù„ÙƒØ§Ù…Ù„Ø© Ù…Ù† Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª)
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
        // Ø­Ø§Ù„Ø© ØªØ£ÙƒÙŠØ¯ Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ â€” Ø¬Ø§Ù‡Ø²Ø© Ù„Ø¯Ø¹Ù… Ù†Ø¸Ø§Ù… Ø§Ù„ØªØ­Ù‚Ù‚ Ù„Ø§Ø­Ù‚Ø§Ù‹
        $userData['email_verified'] = !is_null($user->email_verified_at);
        // Ø±Ø§Ø¨Ø· ØµÙˆØ±Ø© Ø§Ù„Ø­Ø³Ø§Ø¨ Ø¥Ù† ÙˆØ¬Ø¯Øª
        $userData['avatar_url'] = (!empty($user->avatar) && Schema::hasColumn('users', 'avatar'))
            ? $request->getSchemeAndHttpHost() . '/storage/' . $user->avatar
            : null;

        return response()->json([
            'status' => true,
            'data'   => $userData
        ]);
    }

    // 7ï¸âƒ£ ØªÙØ¹ÙŠÙ„/Ø¥Ù„ØºØ§Ø¡ ØªÙØ¹ÙŠÙ„ Ø§Ù„ÙƒÙˆØ±Ø³ (Publish Toggle)
    public function togglePublish($id)
    {
        $course = Course::findCourseSafely($id);

        // Ø­ÙØ¸ Ø§Ù„Ø­Ø§Ù„Ø© Ø§Ù„Ù‚Ø¯ÙŠÙ…Ø© Ù‚Ø¨Ù„ Ø§Ù„ØªØ¨Ø¯ÙŠÙ„
        $wasComingSoon = (bool) $course->is_coming_soon;

        $course->is_coming_soon = !$course->is_coming_soon;
        $course->save();

        // ðŸŽ‰ Ø¹Ù†Ø¯ Ù†Ø´Ø± Ø§Ù„ÙƒÙˆØ±Ø³ (Ø§Ù†ØªÙ‚Ø§Ù„ ÙØ¹Ù„ÙŠ Ù…Ù† is_coming_soon=true Ø¥Ù„Ù‰ false ÙÙ‚Ø·):
        // Ø£Ø±Ø³Ù„ Ø¥Ø´Ø¹Ø§Ø±Ø§Ù‹ Ù„Ù„Ø·Ù„Ø§Ø¨ Ø§Ù„Ù…Ø­Ø¬ÙˆØ²ÙŠÙ† Ø¹Ø¨Ø± Queue.
        if ($wasComingSoon && !$course->is_coming_soon) {
            $this->notifyCoursePublished($course);
        }

        return response()->json([
            'status' => true,
            'message' => $course->is_coming_soon ? 'ØªÙ… Ø¥Ø®ÙØ§Ø¡ Ø§Ù„ÙƒÙˆØ±Ø³ (ÙˆØ¶Ø¹ Ø§Ù„Ø§Ù†ØªØ¸Ø§Ø±)' : 'ØªÙ… Ù†Ø´Ø± Ø§Ù„ÙƒÙˆØ±Ø³ Ø¨Ù†Ø¬Ø§Ø­',
            'data' => $course
        ]);
    }

    /**
     * Ø¥Ø´Ø¹Ø§Ø± "Ø§Ù„ÙƒÙˆØ±Ø³ Ø£ØµØ¨Ø­ Ù…ØªØ§Ø­Ø§Ù‹" Ù„Ù„Ø·Ù„Ø§Ø¨ Ø§Ù„Ø°ÙŠÙ† Ø­Ø¬Ø²ÙˆØ§ Ø§Ù„ÙƒÙˆØ±Ø³ ÙÙ‚Ø·.
     * ÙŠÙØ±Ø³Ù„ Ø¹Ø¨Ø± Queue Ù„Ù…Ù†Ø¹ Timeout Ø¹Ù†Ø¯ ÙˆØ¬ÙˆØ¯ Ø¹Ø¯Ø¯ ÙƒØ¨ÙŠØ± Ù…Ù† Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙŠÙ†.
     * ÙŠØ³ØªØ®Ø¯Ù… type = course_available Ù„Ù…Ù†Ø¹ Ø§Ù„ØªÙƒØ±Ø§Ø± Ø¹Ø¨Ø± PushNotificationService.
     */
    private function notifyCoursePublished(Course $course): void
    {
        try {
            // ÙÙ‚Ø· Ø§Ù„Ù…Ø³ØªØ®Ø¯Ù…ÙˆÙ† Ø§Ù„Ø°ÙŠÙ† Ø­Ø¬Ø²ÙˆØ§ Ø§Ù„ÙƒÙˆØ±Ø³ (course_reservations)
            $userIds = \DB::table('course_reservations')
                ->where('course_id', $course->id)
                ->pluck('user_id')
                ->unique()
                ->values()
                ->toArray();

            if (empty($userIds)) {
                \Log::info("Course #{$course->id} published â€” no reservations found, skipping notification.");
                return;
            }

            $title = 'Ø§Ù„ÙƒÙˆØ±Ø³ Ø£ØµØ¨Ø­ Ù…ØªØ§Ø­Ù‹Ø§ ðŸŽ‰';
            $body = "Ø§Ù„ÙƒÙˆØ±Ø³ '{$course->title}' Ø§Ù„Ø°ÙŠ Ù‚Ù…Øª Ø¨Ø­Ø¬Ø²Ù‡ Ø£ØµØ¨Ø­ Ù…ØªØ§Ø­Ù‹Ø§ Ø§Ù„Ø¢Ù†.";

            \App\Jobs\SendPushNotificationJob::dispatch(
                $userIds,
                $title,
                $body,
                [
                    'type' => 'course_available',
                    'course_id' => (string) $course->id,
                ],
                null, // Ù„Ø§ ØªÙˆØ¬Ø¯ ØµÙˆØ±Ø©
                $course->id,
                'course_available', // type for de-duplication
                null // Ù„Ø§ ÙŠÙˆØ¬Ø¯ Ø³Ø¬Ù„ NotificationSend Ù„Ù‡Ø°Ø§ Ø§Ù„Ù†ÙˆØ¹
            );

            \Log::info("Course #{$course->id} published â€” dispatched notification job for " . count($userIds) . " reserved users.");
        } catch (\Throwable $e) {
            // ÙØ´Ù„ Ø§Ù„Ø¥Ø´Ø¹Ø§Ø±Ø§Øª Ù„Ø§ ÙŠÙ…Ù†Ø¹ Ù†Ø´Ø± Ø§Ù„ÙƒÙˆØ±Ø³ Ø£Ø¨Ø¯Ø§Ù‹
            \Log::error('notifyCoursePublished error for course #' . $course->id . ': ' . $e->getMessage());
        }
    }

    // 7ï¸âƒ£ Ø­Ø°Ù Ø¯Ø±Ø³ Ù…Ø¹ Ù…Ù„ÙØ§ØªÙ‡ ÙˆØ£Ø³Ø¦Ù„ØªÙ‡
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
                'message' => 'ØªÙ… Ø­Ø°Ù Ø§Ù„Ø¯Ø±Ø³ ÙˆÙ…Ù„ÙØ§ØªÙ‡ Ø¨Ù†Ø¬Ø§Ø­'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting lesson #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'ÙØ´Ù„ ÙÙŠ Ø­Ø°Ù Ø§Ù„Ø¯Ø±Ø³: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 8ï¸âƒ£ Ø­Ø°Ù Ù…Ø³ØªÙˆÙ‰ Ø¨Ø¬Ù…ÙŠØ¹ Ø¯Ø±ÙˆØ³Ù‡ ÙˆÙ…Ù„ÙØ§ØªÙ‡ ÙˆØ£Ø³Ø¦Ù„ØªÙ‡
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
                'message' => 'ØªÙ… Ø­Ø°Ù Ø§Ù„Ù…Ø³ØªÙˆÙ‰ ÙˆÙ…Ø­ØªÙˆÙŠØ§ØªÙ‡ Ø¨Ø§Ù„ÙƒØ§Ù…Ù„'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting level #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'ÙØ´Ù„ ÙÙŠ Ø­Ø°Ù Ø§Ù„Ù…Ø³ØªÙˆÙ‰: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 9ï¸âƒ£ Ø­Ø°Ù Ù…Ø³ØªØ®Ø¯Ù… Ù†Ù‡Ø§Ø¦ÙŠØ§Ù‹
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
                'message' => 'ØªÙ… Ø­Ø°Ù Ø§Ù„Ø­Ø³Ø§Ø¨ Ù†Ù‡Ø§Ø¦ÙŠØ§Ù‹ Ø¨Ù†Ø¬Ø§Ø­'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting user #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'ÙØ´Ù„ ÙÙŠ Ø­Ø°Ù Ø§Ù„Ø­Ø³Ø§Ø¨: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ðŸ”Ÿ Ø¥Ø¯Ø§Ø±Ø© Ø§Ù„Ø¨Ø§Ù‚Ø§Øª (Plans) Ù„Ù„ÙƒÙˆØ±Ø³Ø§Øª
    /**
     * Ø¬Ù„Ø¨ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø¨Ø§Ù‚Ø§Øª Ù„ÙƒÙˆØ±Ø³ Ù…Ø¹ÙŠÙ†
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
     * Ø¥Ø¶Ø§ÙØ© Ø¨Ø§Ù‚Ø© Ø¬Ø¯ÙŠØ¯Ø© Ù„ÙƒÙˆØ±Ø³
     */
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

         // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ø±Ø§Ø¨Ø· Ø§Ù„ÙØ±ÙŠØ¯ ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ø¥Ø°Ø§ Ù„Ù… ÙŠÙØ¯Ø®Ù„
         $slug = $request->slug;
         if (empty($slug)) {
             $slug = Str::slug($request->name ?: 'plan') . '-' . substr(uniqid(), -6);
         }
         // Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† Ø¹Ø¯Ù… ØªÙƒØ±Ø§Ø±Ù‡ Ù„Ù†ÙØ³ Ø§Ù„ÙƒÙˆØ±Ø³
         while (CoursePlan::where('course_id', $courseId)->where('slug', $slug)->exists()) {
             $slug = Str::slug($request->name ?: 'plan') . '-' . rand(1000, 9999);
         }

         // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø±ÙØ¹ ØµÙˆØ±Ø© Ø§Ù„Ø¨Ø§Ù‚Ø©
         $imagePath = null;
         if ($request->hasFile('image')) {
             $file = $request->file('image');
             $stored = $file->store('plans', 'public');
             $imagePath = 'storage/' . $stored;
         } elseif ($request->filled('image') && is_string($request->image)) {
             $imagePath = $request->image;
         }

         // Ø§Ø­ØªØ³Ø§Ø¨ Ø§Ù„Ø³Ø¹Ø± ÙˆØ§Ù„ØªØ®ÙÙŠØ¶ ÙˆÙ…Ø±Ø§Ø¹Ø§Ø© Ø§Ù„Ø¨Ø§Ù‚Ø© Ø§Ù„Ù…Ø¬Ø§Ù†ÙŠØ©
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

         // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø¬Ø¯Ø§ÙˆÙ„ Ø§Ù„Ø£Ø³Ø¹Ø§Ø± Ù„Ù„Ø¹Ù…Ù„Ø§Øª Ø§Ù„Ù…ØªØ¹Ø¯Ø¯Ø©
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
             'message' => 'ØªÙ…Øª Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ø¨Ø§Ù‚Ø© Ø¨Ù†Ø¬Ø§Ø­',
             'data' => $plan
         ]);
     }

    /**
     * ØªØ¹Ø¯ÙŠÙ„ Ø¨Ø§Ù‚Ø© Ù…ÙˆØ¬ÙˆØ¯Ø©
     */
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
        if ($request->has('what_you_will_learn')) $plan->what_you_will_learn = $request->what_you_will_learn;
        if ($request->has('currency')) $plan->currency = $request->currency;
        if ($request->has('duration_days')) $plan->duration_days = $request->duration_days;
        if ($request->has('duration_type')) $plan->duration_type = $request->duration_type;
        if ($request->has('is_active')) $plan->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        if ($request->has('sort_order')) $plan->sort_order = $request->sort_order;

        // Ù…Ø¹Ø§Ù„Ø¬Ø© Ø±ÙØ¹ ØµÙˆØ±Ø© Ø¬Ø¯ÙŠØ¯Ø© Ù„Ù„Ø¨Ø§Ù‚Ø©
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $stored = $file->store('plans', 'public');
            $plan->image = 'storage/' . $stored;
        } elseif ($request->filled('image') && is_string($request->image)) {
            $plan->image = $request->image;
        }

        // ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¨Ø§Ù‚Ø© Ø§Ù„Ù…Ø¬Ø§Ù†ÙŠØ© ÙˆØ§Ù„Ø£Ø³Ø¹Ø§Ø±
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

            // Ø§Ø­ØªØ³Ø§Ø¨ Ø§Ù„Ø³Ø¹Ø± Ø§Ù„ÙØ¹Ù„ÙŠ Ø§Ù„ÙØ¹Ø§Ù„
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
            'message' => 'ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¨Ø§Ù‚Ø© Ø¨Ù†Ø¬Ø§Ø­',
            'data' => $plan
        ]);
    }

    /**
     * Ø­Ø°Ù Ø¨Ø§Ù‚Ø©
     */
    public function deleteCoursePlan($id)
    {
        try {
            $plan = CoursePlan::findOrFail($id);
            $plan->delete();

            return response()->json([
                'status' => true,
                'message' => 'ØªÙ… Ø­Ø°Ù Ø§Ù„Ø¨Ø§Ù‚Ø© Ø¨Ù†Ø¬Ø§Ø­'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting plan #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'ÙØ´Ù„ ÙÙŠ Ø­Ø°Ù Ø§Ù„Ø¨Ø§Ù‚Ø©: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 1ï¸âƒ£1ï¸âƒ£ Ø¥Ø¯Ø§Ø±Ø© Ø±Ø¨Ø· Ø§Ù„Ù…Ø­Ø§Ø¶Ø±Ø§Øª Ø¨Ø§Ù„Ø¨Ø§Ù‚Ø§Øª (Plan Lessons)
    /**
     * Ø¬Ù„Ø¨ Ø§Ù„Ù…Ø­Ø§Ø¶Ø±Ø§Øª Ø§Ù„Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ø¨Ø§Ù‚Ø© Ù…Ø¹ÙŠÙ†Ø©
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
     * Ø±Ø¨Ø· Ù…Ø­Ø§Ø¶Ø±Ø© Ø¨Ø¨Ø§Ù‚Ø© (Ø£Ùˆ Ù…Ø¬Ù…ÙˆØ¹Ø©/Ù…Ø³ØªÙˆÙ‰)
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
            'message' => 'ØªÙ… Ø±Ø¨Ø· Ø§Ù„Ù…Ø­Ø§Ø¶Ø±Ø© Ø¨Ø§Ù„Ø¨Ø§Ù‚Ø© Ø¨Ù†Ø¬Ø§Ø­',
            'data' => $planLesson
        ]);
    }

    /**
     * Ø¥Ø²Ø§Ù„Ø© Ø±Ø¨Ø· Ù…Ø­Ø§Ø¶Ø±Ø© Ù…Ù† Ø¨Ø§Ù‚Ø©
     */
    public function removeLessonFromPlan($id)
    {
        try {
            $planLesson = PlanLesson::findOrFail($id);
            $planLesson->delete();

            return response()->json([
                'status' => true,
                'message' => 'ØªÙ… Ø¥Ø²Ø§Ù„Ø© Ø§Ù„Ù…Ø­Ø§Ø¶Ø±Ø© Ù…Ù† Ø§Ù„Ø¨Ø§Ù‚Ø© Ø¨Ù†Ø¬Ø§Ø­'
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing lesson from plan #' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'ÙØ´Ù„ ÙÙŠ Ø¥Ø²Ø§Ù„Ø© Ø§Ù„Ù…Ø­Ø§Ø¶Ø±Ø©: ' . $e->getMessage(),
            ], 500);
        }
    }
}
