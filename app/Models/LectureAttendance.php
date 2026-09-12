<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LectureAttendance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'join_time' => 'datetime',
        'leave_time' => 'datetime',
    ];

    public function onlineLecture()
    {
        return $this->belongsTo(OnlineLecture::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
