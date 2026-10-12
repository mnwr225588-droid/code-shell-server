<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ====================================================
 * CoursePlan Model - نموذج باقات الاشتراك للكورسات
 * ====================================================
 * هذا الموديل يمثل باقات الاشتراك المتاحة للكورسات
 * مثل: اشتراك شهري، اشتراك ترم، إلخ
 * ====================================================
 */
class CoursePlan extends Model
{
    /**
     * الحقول المسموح بتعبئتها (Mass Assignable)
     * هذه الحقول يمكن تعبئتها مباشرة عند إنشاء أو تحديث الباقة
     */
    protected $fillable = [
        'course_id',           // معرف الكورس المرتبط بالباقة
        'name',                // اسم الباقة بالعربية
        'name_en',             // اسم الباقة بالإنجليزية
        'slug',                // الرابط الفريد للباقة (مثل: monthly, term_3months)
        'description',         // وصف تفصيلي للباقة
        'image',               // صورة غلاف الباقة
        'price',               // السعر الأساسي للباقة (أو السعر الفعلي)
        'original_price',      // السعر الأصلي قبل التخفيض
        'discount_price',      // السعر المخفض
        'has_discount',        // تفعيل التخفيض
        'discount_percentage', // نسبة التخفيض
        'currency',            // العملة (مثل: EGP, SAR)
        'prices',              // أسعار متعددة العملات (JSON)
        'original_prices',     // أسعار متعددة العملات قبل التخفيض (JSON)
        'discount_prices',     // أسعار متعددة العملات بعد التخفيض (JSON)
        'duration_days',       // مدة الاشتراك بالأيام
        'duration_type',       // نوع المدة (days, months, years)
        'is_active',           // هل الباقة مفعلة ومتاحة للاشتراك
        'is_free',             // هل الباقة مجانية بالكامل
        'what_you_will_learn', // ماذا ستتعلم في هذا الكورس
        'sort_order',          // ترتيب العرض
        'features',            // مميزات الباقة (JSON)
        'metadata',            // بيانات إضافية (JSON)
    ];

    /**
     * الحقول المضافة تلقائياً في الـ Serialization
     */
    protected $appends = [
        'image_url',
        'duration_text',
    ];

    /**
     * تحويل أنواع البيانات تلقائياً عند القراءة والكتابة
     */
    protected $casts = [
        'price' => 'decimal:2',       // تحويل السعر لرقم عشري بمنزلتين
        'original_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'has_discount' => 'boolean',
        'is_free' => 'boolean',       // تحويل لقيمة منطقية
        'prices' => 'array',          // تحويل الأسعار لـ JSON Array
        'original_prices' => 'array',
        'discount_prices' => 'array',
        'features' => 'array',        // تحويل المميزات لـ JSON Array
        'metadata' => 'array',        // تحويل البيانات الإضافية لـ JSON Array
        'is_active' => 'boolean',     // تحويل لقيمة منطقية
    ];

    /**
     * Accessor لرابط الصورة الكامل
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        if (str_starts_with($this->image, 'assets/')) {
            return asset($this->image);
        }

        // Clean leading slash
        $cleanPath = ltrim($this->image, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        return asset('storage/' . $cleanPath);
    }

    /**
     * علاقة الباقة بالكورس (BelongsTo)
     * كل باقة تتبع كورس واحد
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * علاقة الباقة بالاشتراكات (HasMany)
     * كل باقة يمكن أن يكون لها عدة اشتراكات
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CourseSubscription::class, 'plan_id');
    }

    /**
     * دالة Accessor لتحويل مدة الأيام إلى نص مقروء
     * مثال: 30 يوم -> "شهر واحد"، 90 يوم -> "3 أشهر"
     */
    public function getDurationTextAttribute(): string
    {
        $days = $this->duration_days;
        
        // إذا كانت المدة سنة أو أكثر
        if ($days >= 365) {
            $years = floor($days / 365);
            return $years == 1 ? 'سنة واحدة' : "$years سنوات";
        } 
        // إذا كانت المدة شهر أو أكثر
        elseif ($days >= 30) {
            $months = floor($days / 30);
            return $months == 1 ? 'شهر واحد' : "$months أشهر";
        }
        
        // أقل من شهر - عرض بالأيام
        return "$days يوم";
    }

    public function levels()
    {
        return $this->hasMany(Level::class, 'plan_id')->orderBy('order_num', 'asc');
    }

    public function groups()
    {
        return $this->hasMany(CourseGroup::class, 'plan_id');
    }

    public function onlineLectures()
    {
        return $this->hasMany(OnlineLecture::class, 'plan_id');
    }
}
