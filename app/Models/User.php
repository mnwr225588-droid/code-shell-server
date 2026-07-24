<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'first_name',
    'middle_name',
    'last_name',
    'birth_date',
    'email',
    'phone',
    'password',
    'is_active',
    'telegram_chat_id',
    'telegram_username'
])]

#[Hidden([
    'password',
    'remember_token'
])]

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * الكورسات التي قام المستخدم بحجزها
     */
    public function reservedCourses()
    {
        return $this->belongsToMany(Course::class, 'course_reservations', 'user_id', 'course_id')->withTimestamps();
    }
}