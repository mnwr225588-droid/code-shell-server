<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCourse extends Model
{
    use HasFactory;

    protected $table = 'course_reservations'; // أو اسم جدول الربط لديك في القاعدة

    protected $guarded = [];
}