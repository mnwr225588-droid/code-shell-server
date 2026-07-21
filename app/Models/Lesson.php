<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = ['level_id', 'title', 'description', 'thumbnail', 'video_url'];

    // العلاقة: الدرس يحتوي على عدة أسئلة اختبار
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    // العلاقة العكسية: الدرس ينتمي لمستوى واحد
    public function level()
    {
        return $this->belongsTo(Level::class);
    }
}