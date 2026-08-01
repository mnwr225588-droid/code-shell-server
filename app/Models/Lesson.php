<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'level_id',
        'title',
        'description',
        'thumbnail',
        'video_url',
        'order_num',
        'is_optional'
    ];

    protected $appends = ['thumbnail_url', 'video_url_full'];

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
    }

    public function getVideoUrlFullAttribute()
    {
        if (!$this->video_url) return null;
        // If it's already a full URL, return as-is
        if (filter_var($this->video_url, FILTER_VALIDATE_URL)) {
            return $this->video_url;
        }
        return asset('storage/' . $this->video_url);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}