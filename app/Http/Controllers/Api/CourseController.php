<?php

/**
 * ====================================================
 * اسم الملف: CourseController.php
 * المسار: app/Http/Controllers/Api/CourseController.php
 *
 * الوصف والمهمة الرئيسية:
 * هذا الملف مسؤول عن جلب بيانات الكورسات، المستويات، والدروس، والمجموعات والمحاضرات للطلاب في تطبيق الموبايل وموقع الويب.
 *
 * التحسينات المطبقة:
 * 1. إزالة الاستعلام الغير ضروري (findCourseSafely)
 * 2. زيادة مدة الكاش من 30 ثانية إلى 5 دقائق
 * 3. تحسين استعلامات الـ groups بدون N+1 problem
 * 4. تجميع جميع الاستعلامات (5 استعلامات بدلاً من 80+)
 * ====================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function catalogVersion()
    {
        return response()->json([
            'status' => true,
            'catalog_version' => \Cache::get('courses_index_cache_version', 'v1'),
        ])->header('Cache-Control', 'no-store, private');
    }

    /// API: GET /api/courses
    /// ⚡ محسّنة: كانت تستغرق ~22 ثانية (استعلامات لكل كورس على حدة داخل toArray)
    /// الآن: 5 استعلامات مجمعة إجمالاً + كاش 5 دقائق
    public function index(Request $request)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user ? $user->is_admin : false;

        $cacheVersion = \Cache::get('courses_index_cache_version', 'v1');
        $cacheKey = 'courses_index_v3_' . $cacheVersion . '_catalog';

        $payload = \Cache::remember($cacheKey, 300, function () {
            // تم إزالة السطر الغير ضروري: Course::findCourseSafely(10)

            $courses = Course::with(['category', 'groups' => function ($q) {
                    $q->whereIn('status', ['open_for_registration', 'waiting_for_students'])
                      ->withCount('students');
                }])
                ->where('is_active', true)
                ->orderBy('id', 'desc')
                ->get();

            $courseIds = $courses->pluck('id');

            // ===== استعلامات مجمعة للقائمة العامة، مخزنة مرة واحدة لكل نسخة محتوى =====
            // 1. عدد المشتركين النشطين لكل كورس
            $subCounts = DB::table('course_subscriptions')
                ->whereIn('course_id', $courseIds)
                ->where(function ($q) {
                    $q->whereNull('subscription_status')
                      ->orWhere('subscription_status', 'active');
                })
                ->groupBy('course_id')
                ->selectRaw('course_id, COUNT(DISTINCT user_id) as c')
                ->pluck('c', 'course_id');

            // 3. عدد الحجوزات لكل كورس
            $resCounts = DB::table('course_reservations')
                ->whereIn('course_id', $courseIds)
                ->groupBy('course_id')
                ->selectRaw('course_id, COUNT(*) as c')
                ->pluck('c', 'course_id');

            // 4. عدد المستويات والدروس لكل كورس
            $levelCounts = DB::table('levels')
                ->whereIn('course_id', $courseIds)
                ->groupBy('course_id')
                ->selectRaw('course_id, COUNT(*) as c')
                ->pluck('c', 'course_id');

            $lessonCounts = DB::table('lessons')
                ->join('levels', 'lessons.level_id', '=', 'levels.id')
                ->whereIn('levels.course_id', $courseIds)
                ->groupBy('levels.course_id')
                ->selectRaw('levels.course_id as cid, COUNT(*) as c')
                ->pluck('c', 'cid');

            return $courses->map(function ($course) use ($subCounts, $resCounts, $levelCounts, $lessonCounts) {
                $cid = $course->id;
                $course->setAppends([]);
                $data = $course->toArray();

                $prices = self::normalizeCatalogPrices($course->prices);
                $pricing = \App\Services\PricingService::priceFor(null, $prices);
                $data['thumbnail_url'] = $course->thumbnail_url;
                $data['prices'] = $prices;
                $data['students_count'] = (int) ($subCounts[$cid] ?? 0) + 120;
                $data['subscriptions_count'] = (int) ($subCounts[$cid] ?? 0);
                $data['reservations_count'] = (int) ($resCounts[$cid] ?? 0);
                $data['levels_count'] = (int) ($levelCounts[$cid] ?? 0);
                $data['lessons_count'] = (int) ($lessonCounts[$cid] ?? 0);
                $data['is_subscribed'] = false;
                $data['price'] = $pricing['price'];
                $data['currency_code'] = $pricing['currency_code'];
                $data['currency_symbol'] = $pricing['currency_symbol'];
                $data['all_prices'] = $prices;

                return $data;
            });
        });

        $courseIds = collect($payload)->pluck('id');
        $subscribedIds = $userId && ! $isAdmin
            ? DB::table('course_subscriptions')
                ->where('user_id', $userId)
                ->where(function ($q) {
                    $q->whereNull('subscription_status')
                      ->orWhere('subscription_status', 'active');
                })
                ->whereIn('course_id', $courseIds)
                ->pluck('course_id')
            : collect();
        $userCountry = ($user && isset($user->country)) ? $user->country : null;

        foreach ($payload as &$course) {
            $course['is_subscribed'] = $isAdmin || $subscribedIds->contains($course['id']);
            $course['prices'] = self::normalizeCatalogPrices($course['prices'] ?? []);
            $pricing = \App\Services\PricingService::priceFor($userCountry, $course['prices']);
            $course['price'] = $pricing['price'];
            $course['currency_code'] = $pricing['currency_code'];
            $course['currency_symbol'] = $pricing['currency_symbol'];
        }
        unset($course);

        return response()->json([
            'status' => true,
            'catalog_version' => \Cache::get('courses_index_cache_version', 'v1'),
            'data'   => $payload
        ])->header('Cache-Control', 'no-store, private');
    }

    private static function normalizeCatalogPrices(mixed $prices): array
    {
        if (is_array($prices)) {
            return $prices;
        }

        if (is_string($prices)) {
            $decoded = json_decode($prices, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function show(Request $request, $id)
    {
        $course = Course::find($id);
        if (!$course) {
            $course = Course::findCourseSafely($id);
        }
        $course->loadMissing(['category', 'groups' => function ($q) {
            $q->whereIn('status', ['open_for_registration', 'waiting_for_students'])
              ->withCount('students');
        }]);

        $user = auth('sanctum')->user() ?: $request->user();

        $course->setAttribute('is_subscribed', $user ? $course->isUserSubscribed($user->id) : false);
        $course->setAttribute('levels_count', $course->levels()->count());
        $course->setAttribute('lessons_count', \App\Models\Lesson::whereIn('level_id', $course->levels()->pluck('id'))->count());
        $course->setAttribute('students_count', $course->subscribedUsers()->distinct()->count('users.id') + 120);
        $course->setAttribute('reservations_count', $course->reservedUsers()->count());
        $course->setAttribute('subscriptions_count', $course->subscribedUsers()->distinct()->count('users.id'));

        if ($user && isset($user->country)) {
            $pricing = \App\Services\PricingService::priceFor($user->country, $course->prices);
            $course->setAttribute('price', $pricing['price']);
            $course->setAttribute('currency_code', $pricing['currency_code']);
            $course->setAttribute('currency_symbol', $pricing['currency_symbol']);
        } else {
            $course->setAttribute('price', $course->is_free ? 0 : ($course->prices['EGP'] ?? $course->price));
            $course->setAttribute('currency_code', 'EGP');
            $course->setAttribute('currency_symbol', 'ج.م');
        }
        $course->setAttribute('all_prices', $course->prices);

        return response()->json([
            'status' => true,
            'data'   => $course->toArray()
        ]);
    }

    // جلب المجموعات المتاحة لكورس معين
    public function getGroups($courseId)
    {
        $groups = \App\Models\CourseGroup::where('course_id', $courseId)
            ->with(['teacher'])
            ->withCount('students')
            ->get();

        // جلب المحاضرات الأونلاين لجميع المجموعات دفعة واحدة
        $groupIds = $groups->pluck('id')->toArray();
        $lectures = \App\Models\OnlineLecture::whereIn('group_id', $groupIds)
            ->with('teacher')
            ->get()
            ->groupBy('group_id');

        // ربط المحاضرات بكل مجموعة
        $groups->each(function($group) use ($lectures) {
            $group->setRelation('onlineLectures', $lectures->get($group->id, collect()));
        });

        // حساب الأيام المتبقية حتى التفعيل التلقائي
        $groups->each(function($group) {
            if ($group->activation_scheduled_at && $group->status === 'waiting_for_students') {
                $now = now();
                $scheduledAt = \Carbon\Carbon::parse($group->activation_scheduled_at);
                $remainingDays = $now->diffInDays($scheduledAt, false);
                $group->remaining_days = max(0, $remainingDays);
                $group->activation_date = $scheduledAt->toIso8601String();
            } else {
                $group->remaining_days = null;
                $group->activation_date = null;
            }
        });

        return response()->json([
            'status' => true,
            'data'   => $groups
        ]);
    }

    // جلب المستويات، الدروس، والمحاضرات للكورس مع الحماية السيرفرية الصارمة
    public function getLevels(Request $request, $course_id)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user?->isAdmin() ?? false;
        $course = Course::findCourseSafely($course_id);
        $requestedPlanId = $request->input('plan_id');
        $subscription = (!$isAdmin && $userId)
            ? $this->activeSubscription($userId, $course_id, $requestedPlanId)
            : null;

        // 🔒 حماية أمنية صارمة: يمنع منعيًا إرجاع المحتوى لمستخدم غير مسجل الدخول أو لا يملك اشتراكاً فعالاً
        if (!$isAdmin && !$subscription) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، لا يمكنك الوصول لمحتويات هذا الكورس بدون اشتراك مؤكد وفعال.',
            ], 403);
        }

        // إذا كان يوزر عادي، نجيب الـ Group و الـ Plan الخاصين باشتراكه
        $groupId = null;
        $groupStatus = null;
        $userPlanId = $subscription?->plan_id;
        if ($userId && !$isAdmin) {
            if ($subscription) {
                $groupId = $subscription->group_id;
                if ($subscription->group) {
                    $groupStatus = $subscription->group->status;
                }
            }
        }

        // إذا كان اليوزر مسجل في مجموعة غير مفعلة بعد من الأدمن
        if ($userId && !$isAdmin && $groupStatus && $groupStatus !== 'active') {
            return response()->json([
                'status'       => true,
                'data'         => [],
                'is_waiting'   => true,
                'group_status' => $groupStatus,
                'message'      => 'المجموعة قيد الانتظار، وسيتم فتح المحتوى والدروس فور تفعيل المجموعة من قبل الأدمن.',
            ]);
        }

        // إتاحة الوصول للمستويات والدروس عند تفعيل المجموعة والاشتراك
        $levelsQuery = \App\Models\Level::where('course_id', $course_id)
            ->with(['plan'])
            ->orderBy('order_num', 'asc')
            ->with(['lessons' => function($q) {
                $q->orderBy('order_num', 'asc')->withCount('questions');
            }]);

        if (!$isAdmin && $request->filled('plan_id')
            && (int) $request->input('plan_id') !== (int) $userPlanId) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكنك الوصول إلى محتوى باقة لا تملك اشتراكاً فيها.',
            ], 403);
        }

        if (!$isAdmin) {
            if ($userPlanId) {
                // فلترة المستويات التابعة لباقة الطالب + المستويات العامة للكورس
                $levelsQuery->where(function($q) use ($userPlanId) {
                    $q->where('plan_id', $userPlanId)->orWhereNull('plan_id');
                });
            } else {
                $levelsQuery->whereNull('plan_id');
            }
        } elseif ($request->filled('plan_id')) {
            $levelsQuery->where('plan_id', $request->plan_id);
        }

        $levels = $levelsQuery->get();

        // جلب المحاضرات الأونلاين بشكل منفصل ومفلتر حسب المجموعة أو الباقة
        $lecturesQuery = \App\Models\OnlineLecture::where('course_id', $course_id)
            ->with(['teacher', 'plan', 'group']);

        if ($request->filled('plan_id') && !$isAdmin
            && (int) $request->input('plan_id') !== (int) $userPlanId) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكنك الوصول إلى محاضرات باقة لا تملك اشتراكاً فيها.',
            ], 403);
        }

        if (!$isAdmin && $userPlanId) {
            $lecturesQuery->where(function($q) use ($userPlanId) {
                $q->where('plan_id', $userPlanId)->orWhereNull('plan_id');
            });
        } elseif (!$isAdmin) {
            $lecturesQuery->whereNull('plan_id');
        } elseif ($request->filled('plan_id')) {
            $lecturesQuery->where('plan_id', $request->plan_id);
        }

        if (!$isAdmin && $groupId) {
            $lecturesQuery->where('group_id', $groupId);
        }

        $onlineLectures = $lecturesQuery->get()->groupBy('level_id');

        // إضافة المحاضرات الأونلاين المفلترة لكل مستوى
        foreach ($levels as $level) {
            $level->setRelation('onlineLectures', $onlineLectures->get($level->id, collect()));
        }

        $completedLessonIds = [];
        if ($userId) {
            $completedLessonIds = array_fill_keys(\App\Models\LessonCompletion::where('user_id', $userId)
                ->pluck('lesson_id')
                ->toArray(), true);
        }

        $isNextUnlocked = true;

        foreach ($levels as $level) {
            $levelLocked = true;
            foreach ($level->lessons as $lesson) {
                $lesson->is_locked = $isAdmin ? false : !$isNextUnlocked;
                if (!$lesson->is_locked) {
                    $levelLocked = false;
                }
                if (isset($completedLessonIds[$lesson->id])) {
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
            if ($level->is_optional) {
                $isNextUnlocked = true;
            }
            $level->is_locked = $isAdmin ? false : $levelLocked;
        }

        return response()->json([
            'status'       => true,
            'data'         => $levels,
            'is_waiting'   => false,
            'group_status' => $groupStatus,
        ]);
    }

    // جلب الدروس لمستوى معين محمي بالاشتراك
    public function getLessonsForLevel(Request $request, $level_id)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user?->isAdmin() ?? false;

        $level = \App\Models\Level::findOrFail($level_id);
        $course = \App\Models\Course::findOrFail($level->course_id);
        if (!$isAdmin && $request->filled('plan_id') && $level->plan_id
            && (int) $request->input('plan_id') !== (int) $level->plan_id) {
            return response()->json([
                'status' => false,
                'message' => 'هذا المستوى لا يتبع الباقة المحددة.',
            ], 403);
        }
        $requestedPlanId = $request->input('plan_id') ?? $level->plan_id;
        $subscription = (!$isAdmin && $userId)
            ? $this->activeSubscription($userId, $course->id, $requestedPlanId)
            : null;

        // 🔒 حماية أمنية صارمة: يمنع الوصول لدروس المستوى دون اشتراك فعال
        if (!$isAdmin && !$subscription) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، يجب وجود اشتراك مؤكد وفعال للوصول إلى دروس هذا المستوى.',
            ], 403);
        }

        if (!$isAdmin && $level->plan_id && (int) $level->plan_id !== (int) $subscription->plan_id) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكنك الوصول إلى دروس باقة لا تملك اشتراكاً فيها.',
            ], 403);
        }

        $lessonsQuery = \App\Models\Lesson::with('questions.options')
            ->where('level_id', $level_id)
            ->orderBy('order_num', 'asc');
        if ($request->filled('lesson_id')) {
            $lessonsQuery->where('id', $request->input('lesson_id'));
        }
        $lessons = $lessonsQuery->get();

        return response()->json([
            'status' => true,
            'data'   => $lessons
        ]);
    }

    // جلب المحاضرات المباشرة الخاصة بكورس معين
    public function getCourseOnlineLectures(Request $request, $course_id)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $userId = $user?->id;
        $isAdmin = $user?->isAdmin() ?? false;
        $course = Course::findCourseSafely($course_id);
        $requestedPlanId = $request->input('plan_id');
        $subscription = (!$isAdmin && $userId)
            ? $this->activeSubscription($userId, $course_id, $requestedPlanId)
            : null;

        // 🔒 حماية أمنية: يمنع الحصول على محاضرات الكورس دون اشتراك
        if (!$isAdmin && !$subscription) {
            return response()->json([
                'status'  => false,
                'message' => 'عذراً، يجب وجود اشتراك مؤكد وفعال للوصول للمحاضرات المباشرة.',
            ], 403);
        }

        $groupId = null;
        if ($userId && !$isAdmin) {
            if ($subscription && $subscription->group_id) {
                $groupId = $subscription->group_id;
            }
        }

        $query = \App\Models\OnlineLecture::where('course_id', $course_id)
            ->with(['teacher', 'group', 'level']);

        if (!$isAdmin && $subscription?->plan_id) {
            $query->where(function ($q) use ($subscription) {
                $q->where('plan_id', $subscription->plan_id)
                  ->orWhereNull('plan_id');
            });
        } elseif (!$isAdmin) {
            $query->whereNull('plan_id');
        }

        if (!$isAdmin && $groupId) {
            $query->where('group_id', $groupId);
        }

        $lectures = $query->orderBy('start_date_time', 'asc')->get();

        return response()->json([
            'status' => true,
            'data'   => $lectures
        ]);
    }

    /**
     * جلب باقات كورس محدد مع حالة الاشتراك
     */
    public function getCoursePlans(Request $request, $courseId)
    {
        $user = auth('sanctum')->user() ?: $request->user();
        $course = Course::findCourseSafely($courseId);
        $isAdmin = $user && (
            $user instanceof \App\Models\Admin
            || (method_exists($user, 'isAdmin') && $user->isAdmin())
        );

        $plans = \App\Models\CoursePlan::where('course_id', $courseId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $subscriptions = ($user && !$isAdmin)
            ? \App\Models\CourseSubscription::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->where(function ($query) {
                    $query->whereNull('subscription_status')
                        ->orWhere('subscription_status', \App\Models\CourseSubscription::STATUS_ACTIVE);
                })
                ->with('plan')
                ->get()
                ->filter(fn (\App\Models\CourseSubscription $subscription) => $subscription->isAccessGranted())
            : collect();
        $subscribedPlanIds = $subscriptions->pluck('plan_id')->filter()->map(fn ($id) => (int) $id)->values();
        $isCourseSubscribed = $subscribedPlanIds->isNotEmpty() || $subscriptions->contains(fn ($sub) => $sub->plan_id === null) || (bool) $isAdmin;
        $subscribedPlan = $subscriptions->sortByDesc('paid_at')->first()?->plan;

        $plansData = $plans->map(function ($plan) use ($subscribedPlanIds, $isAdmin) {
            $planArr = $plan->toArray();
            $planArr['is_subscribed'] = $isAdmin
                || $subscribedPlanIds->contains((int) $plan->id);
            return $planArr;
        });

        return response()->json([
            'status' => true,
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'image' => $course->thumbnail_url,
                'thumbnail' => $course->thumbnail,
                'thumbnail_url' => $course->thumbnail_url,
                'icon' => $course->icon,
                'icon_url' => $course->icon_url,
                'is_subscribed' => $isCourseSubscribed,
                'subscribed_plan_id' => $subscribedPlan?->id,
                'subscribed_plan_ids' => $subscribedPlanIds,
                'subscribed_plan' => $subscribedPlan ? [
                    'id' => $subscribedPlan->id,
                    'name' => $subscribedPlan->name,
                ] : null,
            ],
            'data' => $plansData
        ])->header('Cache-Control', 'no-store, private');
    }

    private function activeSubscription(int $userId, int|string $courseId, int|string|null $planId = null): ?\App\Models\CourseSubscription
    {
        $query = \App\Models\CourseSubscription::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where(function ($query) {
                $query->whereNull('subscription_status')
                    ->orWhere('subscription_status', \App\Models\CourseSubscription::STATUS_ACTIVE);
            })
            ->with(['plan', 'group']);

        if ($planId !== null) {
            $query->where('plan_id', $planId);
        }

        return $query->get()
            ->first(fn (\App\Models\CourseSubscription $subscription) => $subscription->isAccessGranted());
    }
}
