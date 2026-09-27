<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ====================================================
 * PlanLesson Model - ربط المحاضرات بالباقات
 * ====================================================
 * هذا الموديل يربط المحاضرات (Lessons) بالباقات (Plans)
 * مما يسمح للأدمن بالتحكم في المحاضرات المتاحة لكل باقة
 * ====================================================
 */
class PlanLesson extends Model
{
    /**
     * الحقول المسموح بتعبئتها (Mass Assignable)
     */
    protected $fillable = [
        'plan_id',         // معرف الباقة
        'lesson_id',       // معرف المحاضرة
        'level_id',        // معرف المستوى (اختياري)
        'group_id',        // معرف المجموعة (اختياري)
        'is_accessible',   // هل المحاضرة متاحة
        'metadata',        // بيانات إضافية
    ];

    /**
     * تحويل أنواع البيانات تلقائياً
     */
    protected $casts = [
        'is_accessible' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * علاقة الباقة (BelongsTo)
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(CoursePlan::class);
    }

    /**
     * علاقة المحاضرة (BelongsTo)
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * علاقة المستوى (BelongsTo)
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * علاقة المجموعة (BelongsTo)
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CourseGroup::class);
    }
}
