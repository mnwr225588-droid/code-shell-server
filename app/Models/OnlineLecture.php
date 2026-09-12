<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineLecture extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date_time' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function group()
    {
        return $this->belongsTo(CourseGroup::class, 'group_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attendances()
    {
        return $this->hasMany(LectureAttendance::class);
    }
}
