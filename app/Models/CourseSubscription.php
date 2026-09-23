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
     * هل يمنح هذا الاشتراك صاحبه صلاحية الوصول للكورس؟
     * (الاشتراك النشط active أو الاشتراكات القديمة legacy التي كانت null)
     */
    public function isGranted(): bool
    {
        return $this->subscription_status === self::STATUS_ACTIVE || is_null($this->subscription_status);
    }
}
