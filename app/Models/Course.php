<?php

namespace App\Models;

/// مودل الـ Course (الكورس)
/// ده المودل الأساسي للمحتوى في المنصة.
/// بيربط بين:
/// 1. Category (القسم اللي بينتمي ليه)
/// 2. Levels (المستويات الخاصة بالكورس)
/// 3. Users (من خلال علاقات الحجز والاشتراك)
/// ⚠️ مهم: المودل بيعمل Appends لخصائص كتير مش موجودة في الداتا بيز (زي price, currency, is_subscribed) 
/// علشان يوفر بيانات جاهزة للـ API من غير ما يضطر الـ Controller يحسبها لكل كورس.

use App\Services\PricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'thumbnail',
        'is_free',
        'price',
        'original_price',
        'discount_percentage',
        'has_discount',
        'prices',
        'is_active',
        'is_coming_soon',
        'sort_order',
        'duration',
        'difficulty',
        'features',
        'what_will_learn',
    ];

    protected $casts = [
        'is_free'        => 'boolean',
        'is_active'      => 'boolean',
        'is_coming_soon' => 'boolean',
        'has_discount'   => 'boolean',
        'price'          => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'prices'         => 'json',
        'features'       => 'json',
        'what_will_learn'=> 'json',
    ];

    protected $appends = [
        'thumbnail_url', 
        'reservations_count', 
        'subscriptions_count',
        'is_subscribed',
        'levels_count',
        'lessons_count',
        'students_count',
        'price',
        'currency_code',
        'currency_symbol',
    ];

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }

    // --- Dynamic attributes & defaults for Course Subscription Dialog ---

    public function getIsSubscribedAttribute()
    {
        $user = auth('sanctum')->user();
        if (!$user) {
            return false;
        }
        // حساب الأدمن (من جدول users أو admins) يملك وصولاً كاملاً لكل الكورسات دون اشتراك.
        if ($user instanceof \App\Models\Admin || (method_exists($user, 'isAdmin') && $user->isAdmin())) {
            return true;
        }
        return $this->subscribedUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * هل المستخدم يملك صلاحية الوصول لهذا الكورس؟
     * (الأدمن: دائماً؛ غيره: الاشتراك الفعلي في جدول الاشتراكات)
     */
    public function isUserSubscribed($userId): bool
    {
        if (!$userId) {
            return false;
        }
        $user = \App\Models\User::find($userId);
        if ($user && $user->isAdmin()) {
            return true;
        }

        $subscription = \DB::table('course_subscriptions')
            ->where('user_id', $userId)
            ->where('course_id', $this->id)
            ->where(function ($q) {
                $q->whereNull('subscription_status')
                  ->orWhere('subscription_status', 'active');
            })
            ->first();

        if (!$subscription) {
            return false;
        }

        // فحص انتهاء الاشتراك بناءً على تاريخ نزول أول محاضرة أونلاين
        $firstLectureDate = \DB::table('online_lectures')
            ->where('course_id', $this->id)
            ->orderBy('created_at', 'asc')
            ->value('created_at');

        if ($firstLectureDate) {
            $metadata = json_decode($subscription->metadata ?? '{}', true);
            $planType = $metadata['plan_type'] ?? 'monthly';
            $durationDays = ($planType === 'term_3months') ? 90 : 30; // 3 months or 1 month

            $startDate = \Carbon\Carbon::parse($firstLectureDate);
            $expiryDate = $startDate->copy()->addDays($durationDays);

            if (\Carbon\Carbon::now()->greaterThan($expiryDate)) {
                \DB::table('course_subscriptions')
                    ->where('id', $subscription->id)
                    ->update(['subscription_status' => 'expired', 'expired_at' => now()]);
                return false;
            }
        }

        return true;
    }

    public function getLevelsCountAttribute()
    {
        return $this->levels()->count();
    }

    public function getLessonsCountAttribute()
    {
        // Get all level IDs for this course
        $levelIds = $this->levels()->pluck('id');
        return \App\Models\Lesson::whereIn('level_id', $levelIds)->count();
    }

    public function getStudentsCountAttribute()
    {
        // Actual subscribed count + 120 (base modifier to look popular)
        return $this->subscribedUsers()->count() + 120;
    }

    public function getDurationAttribute($value)
    {
        return $value ?? '12 ساعة';
    }

    public function getDifficultyAttribute($value)
    {
        return $value ?? 'مبتدئ';
    }

    public function getFeaturesAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [
            'وصول كامل لجميع محتويات الكورس مدى الحياة',
            'شهادة إتمام معتمدة بعد اجتياز الاختبارات',
            'تطبيق عملي ومشاريع حقيقية لتعزيز الفهم',
            'دعم ومتابعة مستمرة من فريق كود شيل',
        ];
    }

    public function getWhatWillLearnAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [
            'المفاهيم والأساسيات البرمجية بشكل مبسط وعملي',
            'مهارات حل المشكلات والتفكير المنطقي البرمجي',
            'بناء وتصميم وتطوير مشاريع حقيقية خطوة بخطوة',
            'أفضل الممارسات المتبعة في كتابة الأكواد النظيفة',
        ];
    }

    // --- Multi-Currency Pricing ---

    /**
     * مصفوفة أسعار الكورس بكل العملات.
     * عند غياب القيم تُستخدم الأسعار الافتراضية الثابتة (18 عملة).
     */
    public function getPricesAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * السعر المناسب لدولة المستخدم المسجلة في حسابه.
     * (الكورس المجاني = 0 دائماً)
     */
    public function getPriceAttribute(): float
    {
        if ($this->is_free) {
            return 0.0;
        }
        $prices = $this->prices ?? [];
        if (empty($prices)) {
            $prices = PricingService::defaults();
        }
        $country = auth('sanctum')->user()?->country;
        return PricingService::priceFor($country, $prices)['price'];
    }

    /** كود العملة المناسب لدولة المستخدم (EGP افتراضياً). */
    public function getCurrencyCodeAttribute(): string
    {
        if ($this->is_free) {
            return 'EGP';
        }
        $prices = $this->prices ?? [];
        if (empty($prices)) {
            $prices = PricingService::defaults();
        }
        $country = auth('sanctum')->user()?->country;
        return PricingService::priceFor($country, $prices)['currency_code'];
    }

    /** الرمز النصي للعملة (ج.م، ر.س، ₪ ...). */
    public function getCurrencySymbolAttribute(): string
    {
        if ($this->is_free) {
            return 'ج.م';
        }
        $prices = $this->prices ?? [];
        if (empty($prices)) {
            $prices = PricingService::defaults();
        }
        $country = auth('sanctum')->user()?->country;
        return PricingService::priceFor($country, $prices)['currency_symbol'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function levels()
    {
        return $this->hasMany(Level::class);
    }

    public function reservedUsers()
    {
        return $this->belongsToMany(User::class, 'course_reservations', 'course_id', 'user_id')->withTimestamps();
    }

    public function subscribedUsers()
    {
        return $this->belongsToMany(User::class, 'course_subscriptions', 'course_id', 'user_id')
            ->where(function ($query) {
                $query->whereNull('course_subscriptions.subscription_status')
                      ->orWhere('course_subscriptions.subscription_status', 'active');
            })
            ->withPivot(['group_id', 'subscription_status', 'payment_status', 'amount'])
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'course_subscriptions', 'course_id', 'user_id')
            ->withPivot(['group_id', 'subscription_status', 'payment_status', 'amount'])
            ->withTimestamps();
    }

    public function getReservationsCountAttribute()
    {
        if ($this->relationLoaded('reservedUsers')) {
            return $this->reservedUsers->count();
        }
        return $this->reservedUsers()->count();
    }

    public function getSubscriptionsCountAttribute()
    {
        if ($this->relationLoaded('subscribedUsers')) {
            return $this->subscribedUsers->count();
        }
        return $this->subscribedUsers()->count();
    }

    public function groups()
    {
        return $this->hasMany(CourseGroup::class);
    }

    public function onlineLectures()
    {
        return $this->hasMany(OnlineLecture::class);
    }

    /**
     * البحث عن كورس البكالوريا بشكل مضمون بغض النظر عن ترتيب قاعدة البيانات.
     *
     * ⚠️ مطابقة النصوص العربية داخل SQL (LIKE / =) غير موثوقة على قاعدة بيانات الإنتاج،
     * (وهذا ما تسبب سابقاً في إنشاء عشرات الكورسات المكررة)، لذلك:
     * 1. نحاول أولاً البحث عبر تصنيف البكالوريا بـ slug إنجليزي (ASCII آمن دائماً).
     * 2. ثم نرتب الكورسات بالمعرف ونطابق العنوان داخل PHP (str_contains يعمل على
     *    مستوى البايتات ولا يتأثر بمشاكل collation قاعدة البيانات).
     */
    public static function findBaccalaureateCourse()
    {
        // slug إنجليزي (ASCII) = مطابقة آمنة على أي قاعدة بيانات — بشرط وجود العمود
        if (\Schema::hasTable('categories') && \Schema::hasColumn('categories', 'slug')) {
            $category = Category::where('slug', 'baccalaureate')->first();
            if ($category) {
                $byCategory = self::where('category_id', $category->id)->orderBy('id')->first();
                if ($byCategory) {
                    return $byCategory;
                }
            }
        }

        // مطابقة العنوان داخل PHP — تعمل حتى لو كانت مطابقة العربية معطلة في SQL
        return self::orderBy('id')->get()
            ->first(fn ($c) => str_contains((string) $c->title, 'بكالوريا'));
    }

    /**
     * دمج كورسات البكالوريا المكررة في كورس واحد (الأقدم بأقل معرف) وحذف الباقي.
     * يُعاد توجيه كل الجداول الأبناء (مستويات، مجموعات، اشتراكات، حجوزات...) للكورس المحتفظ به.
     *
     * @return array إحصائيات العملية: عدد المحذوف ومعرف الكورس المحتفظ به
     */
    public static function deduplicateBaccalaureate(): array
    {
        $bacCourses = self::orderBy('id')->get()
            ->filter(fn ($c) => str_contains((string) $c->title, 'بكالوريا'))
            ->values();

        if ($bacCourses->count() <= 1) {
            return ['deleted' => 0, 'kept_id' => $bacCourses->first()?->id];
        }

        $keeper  = $bacCourses->first();
        $dupeIds = $bacCourses->skip(1)->pluck('id')->all();

        \DB::transaction(function () use ($dupeIds, $keeper) {
            // 1. إعادة ربط الجداول الأبناء بالكورس المحتفظ به قبل الحذف
            foreach ([
                'levels',
                'sections',
                'online_lectures',
                'course_groups',
                'course_reservations',
                'course_plans',
                'transactions',
            ] as $table) {
                if (\Schema::hasTable($table) && \Schema::hasColumn($table, 'course_id')) {
                    \DB::table($table)->whereIn('course_id', $dupeIds)->update(['course_id' => $keeper->id]);
                }
            }

            // 2. الاشتراكات: احترام القيد الفريد (user_id, course_id) — حذف أي تعارض أولاً
            if (\Schema::hasTable('course_subscriptions')) {
                $dupeSubUsers = \DB::table('course_subscriptions')
                    ->whereIn('course_id', $dupeIds)->pluck('user_id');

                \DB::table('course_subscriptions')
                    ->where('course_id', $keeper->id)
                    ->whereIn('user_id', $dupeSubUsers)
                    ->delete();

                \DB::table('course_subscriptions')
                    ->whereIn('course_id', $dupeIds)
                    ->update(['course_id' => $keeper->id]);
            }

            // 3. حذف النسخ المكررة
            self::whereIn('id', $dupeIds)->delete();
        });

        \Log::info('Baccalaureate courses deduplicated', [
            'kept_id'     => $keeper->id,
            'deleted_ids' => $dupeIds,
        ]);

        return ['deleted' => count($dupeIds), 'kept_id' => $keeper->id];
    }

    /**
     * البحث عن الكورس بأمان وإنشاء كورس البكالوريا تلقائياً إذا لم يكن موجوداً إطلاقاً.
     *
     * الإصلاح: النسخة القديمة كانت تعتمد على LIKE عربي داخل SQL يعمل بشكل غير موثوق،
     * فكان يفشل في العثور على الكورس الموجود وينشئ نسخة مكررة عند كل طلب.
     * الآن: المطابقة تتم داخل PHP، والإنشاء يتم مرة واحدة فقط تحت قفل ذري مع إعادة فحص.
     */
    public static function findCourseSafely($id)
    {
        // 1. تجربة البحث برقم المعرف المباشر
        $course = self::find($id);
        if ($course) {
            return $course;
        }

        // 2. البحث عن كورس البكالوريا الموجود (بدون إنشاء)
        $bacCourse = self::findBaccalaureateCourse();
        if ($bacCourse) {
            return $bacCourse;
        }

        // 3. لا يوجد أي كورس بكالوريا — إنشاء واحد فقط تحت قفل ذري لمنع التكرار المتزامن
        $defaults = fn () => [
            'category_id'   => Category::firstOrCreate(['name' => 'المناهج التعليمية'])->id,
            'description'   => 'كورس متخصص في شرح منهج البرمجة لثانوية عامة (ثانية بكالوريا) - محاضرات أونلاين مباشرة مع مدرسين متخصصين. يغطي جميع مفاهيم البرمجة المقررة في المنهج الوزاري مع شرح مفصل وحل أسئلة امتحانية.',
            'thumbnail'     => null,
            'is_free'       => false,
            'price'         => 100.00,
            'prices'        => [
                'EGP' => 300,
                'USD' => 100,
                'SAR' => 40
            ],
            'is_active'     => true,
            'is_coming_soon'=> false,
            'sort_order'    => 10,
            'duration'      => '90 يوم',
            'difficulty'    => 'متوسط',
        ];

        try {
            return \Cache::lock('create_baccalaureate_course', 10)->block(5, function () use ($defaults) {
                // إعادة فحص داخل القفل (Double-Checked Locking) لمنع سباق الإنشاء
                $existing = self::findBaccalaureateCourse();
                if ($existing) {
                    return $existing;
                }
                return self::create(array_merge($defaults(), ['title' => 'منهج البرمجة ثانية بكالوريا']));
            });
        } catch (\Throwable $e) {
            // احتياطي إذا كان مخزن الكاش لا يدعم الأقفال: فحص أخير ثم إنشاء
            $existing = self::findBaccalaureateCourse();
            if ($existing) {
                return $existing;
            }
            return self::create(array_merge($defaults(), ['title' => 'منهج البرمجة ثانية بكالوريا']));
        }
    }
}