<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadTask extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'float',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
