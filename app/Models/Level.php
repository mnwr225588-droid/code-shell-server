<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = ['course_id', 'title', 'order_num'];

    // العلاقة: المستوى يحتوي على عدة دروس
    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}