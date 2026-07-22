<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'is_free',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    /**
     * الأقسام التابعة للكورس
     */
    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    /**
     * المستخدمون الذين حجزوا الكورس
     */
    public function reservedUsers()
    {
        return $this->belongsToMany(User::class, 'course_reservations')->withTimestamps();
    }

    /**
     * حساب عدد الحاجزين الحقيقي للكورس
     */
    public function getReservationsCountAttribute()
    {
        return $this->reservedUsers()->count();
    }
}