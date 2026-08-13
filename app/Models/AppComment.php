<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppComment extends Model
{
    protected $fillable = ['platform', 'name', 'comment', 'ip_address'];
}