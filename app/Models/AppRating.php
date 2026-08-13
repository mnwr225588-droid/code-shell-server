<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRating extends Model
{
    protected $fillable = ['platform', 'rating', 'ip_address'];

    protected $casts = [
        'rating' => 'integer',
    ];
}