<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Progress extends Model
{
    use HasFactory;

    protected $table = 'progress';

    protected $fillable = [

        'user_id',
        'course_id',
        'last_lesson',
        'progress_percentage',
        'completed',

    ];

    protected $casts = [

        'completed' => 'boolean',
        'progress_percentage' => 'decimal:2',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}