<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadSetting extends Model
{
    protected $fillable = ['platform', 'download_enabled'];

    protected $casts = [
        'download_enabled' => 'boolean',
    ];
}
