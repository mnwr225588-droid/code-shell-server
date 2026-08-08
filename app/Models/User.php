<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'birth_date',
        'email',
        'phone',
        'country',
        'is_admin',
        'password',
        'is_active',
        'telegram_chat_id',
        'telegram_username',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * هل هذا الحساب أدمن (تُفتح له جميع الكورسات دون اشتراك)؟
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * دمج الأسماء تلقائياً لملء حقل name لمنع حدوث خطأ قاعدة البيانات
     */
    protected static function booted()
    {
        static::saving(function ($user) {
            if (\Schema::hasColumn('users', 'name')) {
                $user->name = trim(
                    ($user->first_name ?? '') . ' ' . 
                    ($user->middle_name ?? '') . ' ' . 
                    ($user->last_name ?? '')
                );
            }
        });
    }

    /**
     * الكورسات التي قام المستخدم بحجزها
     */
    public function reservedCourses()
    {
        return $this->belongsToMany(Course::class, 'course_reservations', 'user_id', 'course_id')->withTimestamps();
    }

    /**
     * الكورسات التي اشترك فيها المستخدم
     */
    public function subscribedCourses()
    {
        return $this->belongsToMany(Course::class, 'course_subscriptions', 'user_id', 'course_id')->withTimestamps();
    }
}