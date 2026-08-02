<?php

namespace App\Models;

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
        'price'          => 'decimal:2',
        'features'       => 'json',
        'what_will_learn'=> 'json',
    ];

    protected $appends = [
        'thumbnail_url', 
        'reservations_count', 
        'is_subscribed',
        'levels_count',
        'lessons_count',
        'students_count',
    ];

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }

    // --- Dynamic attributes & defaults for Course Subscription Dialog ---

    public function getIsSubscribedAttribute()
    {
        $userId = auth('sanctum')->id();
        if (!$userId) {
            return false;
        }
        return $this->subscribedUsers()->where('user_id', $userId)->exists();
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
        return $this->belongsToMany(User::class, 'course_subscriptions', 'course_id', 'user_id')->withTimestamps();
    }

    public function getReservationsCountAttribute()
    {
        if ($this->relationLoaded('reservedUsers')) {
            return $this->reservedUsers->count();
        }
        return $this->reservedUsers()->count();
    }
}