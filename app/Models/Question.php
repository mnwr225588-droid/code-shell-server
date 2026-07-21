<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $guarded = [];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function options()
    {
        return $this->hasMany(Option::class);
    }
}