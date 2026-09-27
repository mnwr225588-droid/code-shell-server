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
        'price',               // السعر الأساسي للباقة
        'currency',            // العملة (مثل: EGP, SAR)
        'prices',              // أسعار متعددة العملات (JSON)
        'duration_days',       // مدة الاشتراك بالأيام
        'duration_type',       // نوع المدة (days, months, years)
        'is_active',           // هل الباقة مفعلة ومتاحة للاشتراك
        'sort_order',          // ترتيب العرض
        'features',            // مميزات الباقة (JSON)
        'metadata',            // بيانات إضافية (JSON)
    ];

    /**
     * تحويل أنواع البيانات تلقائياً عند القراءة والكتابة
     */
    protected $casts = [
        'price' => 'decimal:2',       // تحويل السعر لرقم عشري بمنزلتين
        'prices' => 'array',          // تحويل الأسعار لـ JSON Array
        'features' => 'array',        // تحويل المميزات لـ JSON Array
        'metadata' => 'array',        // تحويل البيانات الإضافية لـ JSON Array
        'is_active' => 'boolean',     // تحويل لقيمة منطقية
    ];

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
}
