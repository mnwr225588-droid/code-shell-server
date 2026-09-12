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
}
