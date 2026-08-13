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

        return $this->fullUrl($this->thumbnail);
    }

    public function getVideoUrlFullAttribute()
    {
        if (!$this->video_url) return null;
        if (filter_var($this->video_url, FILTER_VALIDATE_URL)) {
            return $this->video_url;
        }

        return $this->fullUrl($this->video_url);
    }

    /**
     * يبني رابطاً كاملاً وقابلاً للبث لملف مُخزَّن على Cloudflare R2.
     * R2 يدعم Range Requests (206) — لذلك البث المباشر يعمل بشكل مثالي
     * عبر `R2_PUBLIC_URL`، ولا نمرر الملف عبر سيرفر Laravel إطلاقاً.
     */
    private function fullUrl(string $path): string
    {
        $r2 = rtrim((string) config('filesystems.disks.r2.url', env('R2_PUBLIC_URL')), '/');
        if ($r2 !== '') {
            return $r2 . '/' . ltrim($path, '/');
        }
        return asset('storage/' . ltrim($path, '/'));
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