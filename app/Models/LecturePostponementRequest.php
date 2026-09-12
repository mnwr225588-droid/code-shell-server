<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturePostponementRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'new_date' => 'date',
        'new_time' => 'datetime:H:i',
    ];

    public function onlineLecture()
    {
        return $this->belongsTo(OnlineLecture::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
