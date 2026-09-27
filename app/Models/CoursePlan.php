<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoursePlan extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'name_en',
        'slug',
        'description',
        'price',
        'currency',
        'prices',
        'duration_days',
        'duration_type',
        'is_active',
        'sort_order',
        'features',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'prices' => 'array',
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CourseSubscription::class, 'plan_id');
    }

    public function getDurationTextAttribute(): string
    {
        $days = $this->duration_days;
        if ($days >= 365) {
            $years = floor($days / 365);
            return $years == 1 ? 'سنة واحدة' : "$years سنوات";
        } elseif ($days >= 30) {
            $months = floor($days / 30);
            return $months == 1 ? 'شهر واحد' : "$months أشهر";
        }
        return "$days يوم";
    }
}
