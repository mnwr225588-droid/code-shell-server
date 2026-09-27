<?php

/**
 * ====================================================
 * اسم الملف: CourseSubscription.php
 * المسار: app/Models/CourseSubscription.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الموديل (Model) بيمثل جدول اشتراكات الطلاب في الكورسات (course_subscriptions).
 * يدير حالات الاشتراكات، وبيانات الدفع، والربط بالمجموعات والمستخدمين والكورسات.
 * 
 * العلاقات الرئيسية:
 * 1. user: المستخدم صاحب الاشتراك (BelongsTo User)
 * 2. course: الكورس المشترك فيه (BelongsTo Course)
 * 3. group: المجموعة التابع لها الطالب (BelongsTo CourseGroup)
 * ====================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSubscription extends Model
{
    use HasFactory;

    /** اسم الجدول في قاعدة البيانات */
    protected $table = 'course_subscriptions';

    /** ثوابت حالة الاشتراك (Subscription Statuses) */
    public const STATUS_ACTIVE    = 'active';    // اشتراك نشط وفعال
    public const STATUS_PENDING   = 'pending';   // قيد الانتظار لحين تأكيد الدفع
    public const STATUS_CANCELLED = 'cancelled'; // اشتراك ملغى
    public const STATUS_EXPIRED   = 'expired';   // اشتراك منتهي الصلاحية
    public const STATUS_INACTIVE  = 'inactive';  // اشتراك غير مفعل

    /** ثوابت حالة الدفع (Payment Statuses) */
    public const PAYMENT_PAID         = 'paid';         // تم الدفع بنجاح
    public const PAYMENT_PENDING      = 'pending';      // قيد الانتظار
    public const PAYMENT_FAILED       = 'failed';       // فشلت عملية الدفع
    public const PAYMENT_NOT_REQUIRED = 'not_required'; // كورس مجاني (لا يتطلب دفع)
    public const PAYMENT_CANCELLED    = 'cancelled';    // عملية تم إلغاؤها
    public const PAYMENT_EXPIRED      = 'expired';      // عملية منتهية الصلاحية

    /** الحقول المسموح بتعبئتها (Mass Assignable) */
    protected $fillable = [
        'user_id',
        'course_id',
        'group_id',
        'plan_id',
        'subscription_status',
        'payment_status',
        'amount',
        'currency_code',
        'course_price_at_purchase',
        'payment_gateway',
        'gateway_transaction_id',
        'gateway_reference_id',
        'idempotency_key',
        'paid_at',
        'failed_at',
        'cancelled_at',
        'expired_at',
        'started_at',
        'is_expired',
        'failure_reason',
        'metadata',
    ];

    /** تحويل أنواع البيانات عند القراءة والكتابة (Casts) */
    protected $casts = [
        'amount'                   => 'decimal:2',
        'course_price_at_purchase' => 'decimal:2',
        'paid_at'                  => 'datetime',
        'failed_at'                => 'datetime',
        'cancelled_at'             => 'datetime',
        'expired_at'               => 'datetime',
        'started_at'               => 'datetime',
        'is_expired'               => 'boolean',
        'metadata'                 => 'array',
    ];

    /**
     * علاقة المستخدم صاحب الاشتراك
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة الكورس المشترك فيه
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * علاقة المجموعة الدراسية
     */
    public function group()
    {
        return $this->belongsTo(CourseGroup::class, 'group_id');
    }

    /**
     * علاقة الاشتراك بالباقة (Plan)
     * كل اشتراك يتبع باقة واحدة
     */
    public function plan()
    {
        return $this->belongsTo(CoursePlan::class, 'plan_id');
    }

    /**
     * هل يمنح هذا الاشتراك صاحبه صلاحية الوصول للكورس؟
     * يتحقق من:
     * 1. هل الاشتراك منتهي (is_expired)
     * 2. هل تاريخ الانتهاء في الماضي
     * 3. هل حالة الاشتراك نشطة
     */
    public function isGranted(): bool
    {
        // إذا كان الاشتراك منتهي صراحة، رفض الوصول
        if ($this->is_expired) {
            return false;
        }
        
        // إذا كان تاريخ الانتهاء موجود وفي الماضي، رفض الوصول
        if ($this->expired_at && $this->expired_at->isPast()) {
            return false;
        }
        
        // القبول إذا كان الاشتراك نشط أو من الاشتراكات القديمة (null)
        return $this->subscription_status === self::STATUS_ACTIVE || is_null($this->subscription_status);
    }

    /**
     * حساب المدة المتبقية من الاشتراك بالأيام
     * Accessor تلقائي يرجع عدد الأيام المتبقية
     * يرجع 0 إذا لم يكن هناك تاريخ انتهاء
     */
    public function getRemainingDaysAttribute(): int
    {
        // إذا لم يكن هناك تاريخ انتهاء، رجع 0
        if (!$this->expired_at) {
            return 0;
        }
        
        // حساب الفرق بالأيام بين الآن وتاريخ الانتهاء
        // false للفرق في المستقبل (أيام متبقية)
        // max(0, ...) لضمان عدم إرجاع أرقام سالبة
        return max(0, now()->diffInDays($this->expired_at, false));
    }

    /**
     * تحديث حالة الاشتراك بناءً على تاريخ الانتهاء
     * هذه الدالة تستدعى عند كل طلب للتحقق من حالة الاشتراك
     * إذا انتهت المدة، يتم تحديث الحالة تلقائياً
     */
    public function checkAndUpdateExpiry(): void
    {
        // التحقق من وجود تاريخ انتهاء وأنه في الماضي
        if ($this->expired_at && $this->expired_at->isPast()) {
            // تحديث حالة الاشتراك إلى منتهي
            $this->is_expired = true;
            $this->subscription_status = self::STATUS_EXPIRED;
            $this->save();
        }
    }

    /**
     * تفعيل الاشتراك من أول محاضرة أونلاين
     * هذه الدالة تستدعى عند نزول أول محاضرة أونلاين للكورس
     * تحسب تاريخ البدء والانتهاء بناءً على مدة الباقة
     */
    public function activateFromFirstLecture(): void
    {
        // التحقق من وجود باقة مرتبطة بالاشتراك
        if (!$this->plan) {
            return;
        }

        // تعيين تاريخ البدء الآن
        $this->started_at = now();
        
        // حساب تاريخ الانتهاء بإضافة مدة الباقة بالأيام
        $this->expired_at = now()->addDays($this->plan->duration_days);
        
        // تفعيل الاشتراك
        $this->is_expired = false;
        $this->subscription_status = self::STATUS_ACTIVE;
        
        // حفظ التغييرات
        $this->save();
    }
}
