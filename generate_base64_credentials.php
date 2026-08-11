<?php
$path = 'storage/app/firebase/firebase-credentials.json';
if (file_exists($path)) {
    $content = file_get_contents($path);
    echo "\n=== انسخ النص بالأسفل وضعه في متغير FIREBASE_CREDENTIALS_JSON_B64 في Railway ===\n\n";
    echo base64_encode($content);
    echo "\n\n=================================================================================\n";
} else {
    echo "الملف غير موجود في المسار المحلي!";
}
