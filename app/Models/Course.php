<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'thumbnail',
        'is_free',
        'price',
        'is_active',
        'is_coming_soon',
        'sort_order',
    ];

    protected $casts = [
        'is_free'        => 'boolean',
        'is_active'      => 'boolean',
        'is_coming_soon' => 'boolean',
        'price'          => 'decimal:2',
    ];

    protected $appends = ['thumbnail_url'];

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function levels()
    {
        return $this->hasMany(Level::class);
    }

    public function reservedUsers()
    {
        return $this->belongsToMany(User::class, 'course_reservations')->withTimestamps();
    }

    public function getReservationsCountAttribute()
    {
        return $this->reservedUsers()->count();
    }
}