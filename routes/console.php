<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// إعادة معالجة فيديوهات R2 إلى Fast-Start للبث السلس (moov في البداية)
Artisan::command('videos:faststart', function () {
    $this->call(\App\Console\Commands\VideosFastStart::class);
})->purpose('Fix all R2 lesson videos to Fast-Start (streaming)');

// جدولة أمر فحص انتهاء مدة المجموعات وإغلاقها تلقائياً (يعمل كل ساعة)
\Illuminate\Support\Facades\Schedule::command('groups:check-expiry')->hourly();
