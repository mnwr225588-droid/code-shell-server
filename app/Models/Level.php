<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $guarded = [];

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order_num', 'asc');
    }
}