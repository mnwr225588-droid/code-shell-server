<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['lesson_id', 'question_text'];

    // العلاقة: السؤال يحتوي على عدة خيارات (4 خيارات)
    public function options()
    {
        return $this->hasMany(Option::class);
    }

    // العلاقة العكسية: السؤال ينتمي لدرس واحد
    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}