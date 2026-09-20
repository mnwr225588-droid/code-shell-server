<?php

/**
 * ====================================================
 * اسم الملف: cors.php
 * المسار: config/cors.php
 * 
 * الوصف والمهمة الرئيسية:
 * هذا الملف يتولى إعدادات مشاركة الموارد بين الأصول المختلفة (CORS).
 * يسمح بطلبات API القادمة من موقع الويب، وتطبيق الموبايل، والتصفح المحلي (file://).
 * ====================================================
 */

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
