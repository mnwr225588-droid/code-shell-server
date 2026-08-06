<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSend extends Model
{
    protected $guarded = [];

    protected $casts = [
        'course_id'  => 'integer',
        'sent_at'    => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'sent_by');
    }
}
