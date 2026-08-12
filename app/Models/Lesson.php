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
        if (!$this->thumbnail) return null;
        if (filter_var($this->thumbnail, FILTER_VALIDATE_URL)) {
            return $this->thumbnail;
        }
        
        $r2Url = env('R2_PUBLIC_URL');
        if ($r2Url) {
            return rtrim($r2Url, '/') . '/' . ltrim($this->thumbnail, '/');
        }
        return asset('storage/' . $this->thumbnail);
    }

    public function getVideoUrlFullAttribute()
    {
        if (!$this->video_url) return null;
        if (filter_var($this->video_url, FILTER_VALIDATE_URL)) {
            return $this->video_url;
        }

        $r2Url = env('R2_PUBLIC_URL');
        if ($r2Url) {
            return rtrim($r2Url, '/') . '/' . ltrim($this->video_url, '/');
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