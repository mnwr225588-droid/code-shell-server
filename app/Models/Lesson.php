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
        'video_path',
        'thumbnail',
        'order_num'
    ];

    protected $appends = ['video_url', 'thumbnail_url'];

    public function getVideoUrlAttribute()
    {
        if (!$this->video_path) return null;
        // إذا كان الفيديو رابطاً خارجياً (مثل يوتيوب أو سيرفر آخر) يرجعه كما هو، وإلا يرجعه من المجلد المحفوظ
        return filter_var($this->video_path, FILTER_VALIDATE_URL) 
            ? $this->video_path 
            : asset('storage/' . $this->video_path);
    }

    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail ? asset('storage/' . $this->thumbnail) : null;
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