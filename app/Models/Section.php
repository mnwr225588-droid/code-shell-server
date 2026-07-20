<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [

        'course_id',
        'title',
        'description',
        'sort_order',
        'is_active',

    ];

    protected $casts = [

        'is_active' => 'boolean',

    ];

    /**
     * الكورس الذي ينتمي إليه القسم
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * الدروس الموجودة داخل القسم
     */
    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}