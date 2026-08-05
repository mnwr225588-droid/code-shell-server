<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'version_code',
        'version_name',
        'file_path',
        'changelog',
        'downloads_count',
        'is_update',
    ];

    protected $casts = [
        'downloads_count' => 'integer',
        'is_update' => 'boolean',
    ];
}