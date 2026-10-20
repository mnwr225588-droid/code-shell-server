<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'registration_deadline' => 'datetime',
        'duration_days' => 'integer',
        'is_auto_create' => 'boolean',
        'include_all_levels' => 'boolean',
        'activation_scheduled_at' => 'datetime',
        'registration_period_days' => 'integer',
        'admin_telegram_full_alerted_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function onlineLectures()
    {
        return $this->hasMany(OnlineLecture::class, 'group_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'course_subscriptions', 'group_id', 'user_id')->withTimestamps();
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function level()
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    public function plan()
    {
        return $this->belongsTo(CoursePlan::class, 'plan_id');
    }
}
