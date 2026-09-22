<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Teacher extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function courseGroups()
    {
        return $this->hasMany(CourseGroup::class);
    }

    public function onlineLectures()
    {
        return $this->hasMany(OnlineLecture::class);
    }

    public function isAdmin(): bool
    {
        return false;
    }
}
